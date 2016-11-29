<?php
require 'vendor/autoload.php'
require_once('Config.php')

$CONFIG = new Config();

$app = new \Slim\App();

$container = $app->getContainer();

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig("templates", [
        'cache' => false
    ]);

    $basePath = rtrim(str_ireplace('index.php',
				   '', $container['request']->getUri()->getBasePath()),
		      '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));
    return $view;
}

$app->get('/home', function($request, $reponse, $args){
    return $this->view->render($response, 'default.html', []);
});

$app->get('/', function($request, $response, $args){
    return $this->view->render($response, 'default.html',[]);
});
  
$app->get('/sports/{sport}', function($request, $response, $args) {
    if (CONFIG->IsSport($args['sport'])) {
        return $this->view->render($response, 'sports.html', [
            'sport' => $args['sport']
        ]);
    } else {
        return $this->view->render($reponse, '404.html')
    }
});

$app->get('/events/{sport}', function($request, $response, $args) {
    if (CONFIG->IsSport($args['sport'])){
        return '{"testevent":"test"}';
    } else {
        $response.setStatus(404);
    }
});

$app->get('/login', function($request, $response, $args) {
    return $this->View->render($reponse, 'login.html', []);
});

$app->post('/login', function($request, $response, $args) {
    /*Stub*/
    $response.setStatus(200);
});

$app->get('/register', function($request, $response, $args) {
    return $this->view->render($response, 'register.html', []);
});

$app->post('/register', function($request, $response, $args) {
    $data = $request->getPostBody();
    $response.setStatus(200);
});

$app->post('/vote/{event_id}', function($request, $response, $args) {
    $data = $request->getPostBody();
    /*$event_id = $args['event_id'];
    $options_id = $data['option_id'];
    // have a login to set a cookie
    $user_id = $app->getCookie('user_id');
    if (!isset($user_id)){
      user_id = ANON_USER_ID;
    }*/
    /* Logic to update database here, store new optionID for an event
    // Also need to log IP addresses for an event: I think $_SERVER['REMOTE_ADDR'] works
    // Could also write   request::getIp();  ? */
});

$app->run();
