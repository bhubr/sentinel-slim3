<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Middleware that forces Eloquent to be loaded
$app->add( function($request, $response, $next) use($app) {
    $db = $table = $app->getContainer()->get('db');
    return $next($request, $response);
});

// One-time use route to create roles
$app->get('/setup', function (Request $request, Response $response, $args) use ($app) {
    $app->container->sentinel->getRoleRepository()->createModel()->create(array(
        'name'          => 'Admin',
        'slug'          => 'admin',
        'permissions'   => array(
            'user.create' => true,
            'user.update' => true,
            'user.delete' => true
        ),
    ));

    $app->container->sentinel->getRoleRepository()->createModel()->create(array(
        'name'          => 'User',
        'slug'          => 'user',
        'permissions'   => array(
            'user.update' => true
        ),
    ));
});

$app->get('/admin/users', function (Request $request, Response $response, $args) use ($app) {
    $loggedUser = $app->getContainer()->sentinel->check();

    if (!$loggedUser) {
        echo 'You need to be logged in to access this page.';

        return;
    }

    if (!$loggedUser->hasAccess('user.*')) {
        echo "You don't have the permission to access this page.";

        return;
    }

    echo 'Welcome to the admin page.';
});

$app->get('/logout', function (Request $request, Response $response, $args) use ($app) {
    $app->getContainer()->sentinel->logout();

    echo 'Logged out successfuly.';
});

$app->get('/login', function (Request $request, Response $response, $args) use ($app) {
    $app->getContainer()->view->render($response, 'login.html.twig');
    return $response;
});

$app->post('/login', function (Request $request, Response $response, $args) use ($app) {
    $data = $request->getParsedBody();
    $remember = isset($data['remember']) && $data['remember'] == 'on' ? true : false;
    
    try {
        if (!$app->getContainer()->sentinel->authenticate([
                'email' => $data['email'],
                'password' => $data['password'],
            ], $remember)) {

            echo 'Invalid email or password.';

            return;
        } else {
            echo 'You\'re logged in';

            return;
        }
    } catch (Cartalyst\Sentinel\Checkpoints\ThrottlingException $ex) {
        echo "Too many attempts!";

        return;
    } catch (Cartalyst\Sentinel\Checkpoints\NotActivatedException $ex){
        echo "Please activate your account before trying to log in";
        
        return;
    }
});

$app->get('/', function (Request $request, Response $response, $args) use ($app) {
    $app->getContainer()->view->render($response, 'home.html.twig');
    return $response;
});

$app->post('/', function (Request $request, Response $response, $args) use ($app) {
    // we leave validation for another time
    $data = $request->getParsedBody();

    $role = $app->getContainer()->sentinel->findRoleByName('Admin');

    if ($app->getContainer()->sentinel->findByCredentials([
        'login' => $data['email'],
    ])) {
        echo 'User already exists with this email.';

        return;
    }

    $user = $app->getContainer()->sentinel->create([
        'first_name' => $data['firstname'],
        'last_name' => $data['lastname'],
        'email' => $data['email'],
        'password' => $data['password'],
        'permissions' => [
            'user.delete' => false,
        ],
    ]);

    // attach the user to the admin role
    $role->users()->attach($user);

    // create a new activation for the registered user
    $activation = (new Cartalyst\Sentinel\Activations\IlluminateActivationRepository)->create($user);

    //mail($data['email'], "Activate your account", "Click on the link below \n <a href='http://vaprobash.dev/user/activate?code={$activation->code}&login={$user->id}'>Activate your account</a>");
    $baseUrl = $request->getUri()->getBaseUrl();
    echo "Please check your email to complete your account registration. (or just use this <a href='{$baseUrl}/user/activate?code={$activation->code}&login={$user->id}'>link</a>)";
});

$app->get('/user/activate', function (Request $request, Response $response, $args) use ($app) {
    $code = $request->getParam('code');

    $activationRepository = new Cartalyst\Sentinel\Activations\IlluminateActivationRepository;
    $activation = Cartalyst\Sentinel\Activations\EloquentActivation::where("code", $code)->first();

    if (!$activation)
    {
        echo "Activation error!";
        
        return;
    }

    $user = $app->getContainer()->sentinel->findById($activation->user_id);

    if (!$user)
    {
        echo "User not found!";
        
        return;
    }


    if (!$activationRepository->complete($user, $code))
    {
        if ($activationRepository->completed($user))
        {
            echo 'User is already activated. Try to log in.';

            return;
        }

        echo "Activation error!";
        
        return;
    }

    echo 'Your account has been activated. Log in to your account.';

    return;
});


