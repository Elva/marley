<?php

//
// Marley 0.4
// 
// (c) Lasha Tavartkiladze
// Distributed under the MIT license.
//
// Docs and examples: 
// http://marley.elva.org
//


class Marley {

    //
    // List of all global options that will be used as defaults 
    // for all objects and methods inside Marley.
    //
    private $global_options = [];


    //
    // Key/Value list of all shared objects.
    // Each key/value will become a property of the context object.
    //
    private $shared_objects = [];


    //
    // Instance of the MarleyUrlRoute class.
    //
    private $url_route;
 

    //
    // Create a Marley object and optionally specify some default settings.
    //
    public function __construct($options = []) {
        $this->global_options = array_merge($this->global_options, $options);

        $this->include_all_classes();
        $this->url_route = new MarleyUrlRoute($_SERVER['REQUEST_URI']);
    }


    //
    // Get or set global options.
    //
    public function config($options) {
        if (is_array($options)) {
            $this->global_options = array_merge($this->global_options, $options);
        } else if (is_string($options)) {
            $key = $options;
            return $this->global_options[$key];    
        }
    }


    //
    // Add an object to the shared objects list
    //
    public function share($name, $object) {
        $this->shared_objects[$name] = $object;
    }


    //
    // Create a Rails-style REST resource.
    //
    public function resource($name) {
        $this->get(  "/{$name}",            "{$name}#index" );
        $this->get(  "/{$name}/new",        "{$name}#new_" );
        $this->post( "/{$name}/create",     "{$name}#create" );
        $this->get(  "/{$name}/:id/edit",   "{$name}#edit" );
        $this->post( "/{$name}/:id/update", "{$name}#update" );
        $this->post( "/{$name}/:id/delete", "{$name}#delete" );
        $this->get(  "/{$name}/:id",        "{$name}#show" );
    }


    //
    // Shortcut wrapper for GET routes.
    //
    public function get($route, $callback, $route_options = []) {
        return $this->map('GET', $route, $callback, $route_options);
    }


    //
    // Shortcut wrapper for POST routes.
    //
    public function post($route, $callback, $route_options = []) {
        return $this->map('POST', $route, $callback, $route_options);
    }


    // 
    // Map current HTTP method and url to the passed route.
    //
    private function map($method, $route, $callback, $route_options) {
        if ($_SERVER['REQUEST_METHOD'] === $method && $match = $this->url_route->match($route)) {
            if (is_array($match['params'])) {
                foreach ($match['params'] as $key => $value) {
                    $_GET[$key] = $value;
                }
            }
            $this->run($callback, $match['params'], $route_options);
            // Currently we don't support route passing, 
            // so exit execution after the first match.
            exit;
        }
    }


    //
    // Run a callback of the matched route.
    //
    private function run($callback, $route_params, $route_options) {
        $options = array_merge($this->global_options, $route_options);
        $action  = new MarleyAction($callback, $options);

        if ($action->is_controller) {
            $options['templates_sub_dir'] = $action->controller_name;
        } 

        $context = new MarleyContext($options);

        if ($action->is_controller) {
            $context->controller = $action->controller_object;
        }
        
        // Add all shared objects as context's properties.
        foreach($this->shared_objects as $name => $object) {
            if(!$context->$name) {
                $context->$name = is_callable($object) ? $object->bindTo($context) : $object;
            }
        }

        // Call the callback function, pass route paremeters 
        // and bind it to the created context object.
        $action->run($route_params, $context);

        // If action is a controller and we reached this code,
        // it means render() wasn't called, so we call it automatically.
        if ($action->is_controller) {
            $context->render($action->action_name);
        }
    }


    // 
    // Include all required Marley classes.
    //
    private function include_all_classes() {
        require_once 'marley_url_route.class.php';
        require_once 'marley_action.class.php';
        require_once 'marley_template.class.php';
        require_once 'marley_context.class.php';
    }

}
