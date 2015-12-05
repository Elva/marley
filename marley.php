<?php

/*
    Marley

    PHP library for solving common web application development problems:  
    code organization, routing and templating.

    Lasha Tavartkiladze
    2014-11-27

    http://marley.elva.org
*/


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

}





//
// MarleyUrlRoute class wraps-around an url and matches given routes
// to that url with the match() method.
//
// If match was successful, it returns full infromation about the match
// including parameters and their values.
//

class MarleyUrlRoute {

    //
    // Private reference to the url we're trying to match against.
    //
    private $url;
    
    public function __construct($url) {
        $this->url = $url;
    }


    //
    // Match any character except slash `/`
    //
    // If user doesn't specifies a custom regex, this is the pattern 
    // that will be used to extract route parameter values from the url.
    //
    // This is just a pattern not an actual regex, so slash shouldn't be escaped.
    // Escaping will happen later inside the route_to_regex() method.
    //
    // Examples:
    //
    // url     => /artist/queen/innuendo
    // route   => /artist/:name/:album
    // values  => queen, innuendo
    //
    // url     => /track/4d61726c65792031393435
    // route   => /track/:id
    // value   => 4d61726c65792031393435
    //
    const DEFAULT_REGEX_PATTERN_FOR_EXTRACTING_PARAM_VALUES = '([^/]+)';


    //
    // Match colon `:` then any character except slash `/`
    //
    // Examples:
    //
    // route  => /artists/list/:name
    // match  => :name
    //
    // route  => /artists/list/:age([0-9]{3})
    // match  => :age([0-9]{3})
    //
    const REGEX_FOR_EXTRACTING_WHOLE_PARAMS = '/:[^\/]+/';


    //
    // Matches column `:` then any character except left parenthesis `(` and slash `/`
    //
    // Examples:
    //
    // param  => :name([a-z]+)
    // match  => :name
    //
    // param  => :age([0-9]{3})
    // match  => :age
    //
    const REGEX_FOR_EXTRACTING_PARAM_NAMES = '/:[^(\/]+/';


    //
    // Match left parenthesis `(` then any character except slash `/` and then right parenthesis `)`
    //
    // Examples:
    //
    // param  => :name([a-z]+)
    // match  => ([a-z]+)
    //
    // param  => :age([0-9]{3})
    // match  => ([0-9]{3})
    //
    const REGEX_FOR_EXTRACTING_USER_SPECIFIED_REGEXES = '/\([^\/]+\)/';


    //
    // Match the url to a route.
    //
    // Return an array containing all information about the match,
    // including paramaters and their values or FALSE if there is no match.
    //
    // For example, 
    // if the url is `/track/4d61726c65792031393435` and the route is `/track/:id`,
    // this function will return an array like this:
    //
    // [
    //    'url'    => '/track/4d61726c65792031393435',
    //    'route'  => '/track/:id',
    //    'params' => [':id' => '4d61726c65792031393435']
    // ]
    //
    public function match($route) {
        $route_regex  = $this->route_to_regex($route);
        $param_names  = $this->param_names($route);
        $matches      = [];

        if (preg_match($route_regex, $this->url, $matches)) {
            $match = [
                'url'   => $this->url,
                'route' => $route
            ];
            // If there're parameters
            if (count($matches) > 1) {
                $param_values = array_slice($matches, 1);
                
                // Remove unnecessary query string match (if exists) from the list of values.
                if (count($param_values) > count($param_names)) {
                    array_pop($param_values);
                }
                
                $match['params'] = array_combine($param_names, $param_values);
            }
            return $match;
        } else {
            return FALSE;
        }
    }


    //
    // Turn whole route into a regex by replacing each parameter with a corresponding regex pattern.
    //
    // Examples:
    //
    // route    => /music/artists/:name
    // returns  => /^\/music\/artists\/([-\w]+)\/?/
    //
    // route    => /music/artists/:track_id([0-9]+)
    // returns  => /^\/music\/artists\/([0-9]+)\/?/
    //
    // route    => /music/artists/:name/:track_id([0-9]+)
    // returns  => /^\/music\/artists\/([-\w]+)\/([0-9]+)\/?/
    //
    private function route_to_regex($route) {
        // Replace each parameter with corresponding regex pattern.
        $regex = preg_replace_callback($this::REGEX_FOR_EXTRACTING_WHOLE_PARAMS, function($matches) {
            $param = $matches[0];
            return $this->param_to_regex($param);
        }, $route);

        // Escape slashes.
        $regex = str_replace('/', '\/', $regex);

        // Optional ending slash.
        $ending_slash = '\/?';

        // Optional query string.
        $query_string = '(\?[^\/]+)?';

        // Match from the start with an optional slash and query string in the end.
        return '/^' . $regex . $ending_slash . $query_string . '$/';
    }


    //
    // Turn a route parameter into a regex pattern.
    //
    // If user manually specified a regex pattern, we extract and return it,
    // otherwise we return a default pattern.
    //
    // Examples:
    //
    // param    => :name
    // returns  => ([^\]+)
    //
    // param    => :track_id([0-9]+)
    // returns  => ([0-9]+)
    //
    // param    => :album_name([a-zA-Z]+)
    // returns  => ([a-zA-Z]+)
    //
    private function param_to_regex($param) {
        $matches = [];

        if (preg_match($this::REGEX_FOR_EXTRACTING_USER_SPECIFIED_REGEXES, $param, $matches)) {
            $user_specified_regex = $matches[0];
            return $user_specified_regex;
        } else {
            return $this::DEFAULT_REGEX_PATTERN_FOR_EXTRACTING_PARAM_VALUES;
        }
    }


    //
    // Get all parameter names of a route as an array.
    //
    // Examples:
    //
    // route    => /music/artists/:name
    // returns  => [':name']
    //
    // route    => /music/artists/:name/:track_id([0-9]+)
    // returns  => [':name', ':track_id']
    //
    // route    => /msuic/new
    // returns  => []
    //
    private function param_names($route) {
        $matches = [];

        if (preg_match_all($this::REGEX_FOR_EXTRACTING_PARAM_NAMES, $route, $matches)) {
            $param_names = $matches[0]; // The first element is the array we need.
            return $param_names;
        } else {
            return [];
        }
    }

}





//
// MarleyAction class wraps-around a controller class 
// or a callback function and provides functionality
// for calling and assigning $this context to the wrapped
// callback method/function.
//

class MarleyAction {

    //
    // Array of default, controller specific options.
    //
    private $options = [
        'root_dir'                => '',
        'controller_dir'          => '/controllers',
        'controller_file_suffix'  => '_controller.php',
        'controller_class_suffix' => 'Controller'
    ];


    //
    // Reference to an internal callback closure object.
    //
    private $callback_closure;


    //
    // Public properties that expose information about the action object
    // and the type of callback we're dealing with.
    //
    public $is_controller;
    public $controller_object;
    public $controller_name;
    public $action_name;


    //
    // Set default options and create an internal reference to the requested callback function. 
    //
    public function __construct($callback, $options = []) {
        $this->options['root_dir'] = $_SERVER['DOCUMENT_ROOT'];
        $this->options = array_merge($this->options, $options);

        if (is_string($callback)) {
            $this->set_controller_info($callback);
            $this->initialize_controller();
            $this->callback_closure = $this->controller_callback();
        } else {
            $this->callback_closure = $callback;
        }

        if (!is_callable($this->callback_closure)) {
            throw new InvalidArgumentException('Callback function isn\'t callable.');
        }
    }


    //
    // Call the callback function with optional parameters and a context object.
    //
    // $params is an array of paremeters whose elements are passed individually to the callback function.
    // $context is an object which becomes $this inside the callback function.
    //
    public function run($params = null, $context = null) {
        $f = $this->callback_closure;

        if ($context) {
            $f = $f->bindTo($context);
        }

        if ($params) {
            call_user_func_array($f, $params);
        } else {
            $f();
        }
    }


    //
    // Extract controller and action names from a provided string.
    //
    // The string should be in this format: `controller#action`, 
    // where `controller` is a name (without suffix) of a controller class
    // and `action` is a name of a method inside that controller class.
    //
    private function set_controller_info($str) {
        $controller_parts      = explode('#', $str);
        $this->controller_name = $controller_parts[0];
        $this->action_name     = $controller_parts[1];
        $this->is_controller   = true;
    }


    //
    // Dynamically include controller class and initialize it.
    //
    private function initialize_controller() {
        require_once $this->controller_path();
        $class = ucfirst($this->controller_name) . $this->options['controller_class_suffix'];
        $this->controller_object = new $class;
    }


    //
    // Extract the requested callback function from a controller object.
    //
    private function controller_callback() {
        $method_name = str_replace('-', '_', $this->action_name);
        $method = new ReflectionMethod($this->controller_object, $method_name);
        return $method->getClosure($this->controller_object);
    }


    //
    // Determine a controller file path.
    //
    private function controller_path() {
        $controller_directory = $this->options['root_dir'] . $this->options['controller_dir'] . '/' ;
        $controller_filename  = $this->controller_name . $this->options['controller_file_suffix'];
        return $controller_directory . $controller_filename;
    }

}





//
// MarleyTemplate class wraps-around a template directrory and 
// then loads and compiles templates and layouts from that directory.
//
// You can override default options from the constructor or
// directly from the find_and_render() method.
//

class MarleyTemplate {

    //
    // Array of default options.
    //
    private $options = [
        'root_dir'          => '',
        'templates_dir'     => '/views',
        'templates_sub_dir' => '',
        'layouts_dir'       => '/views/layouts',
        'layout'            => 'main',
        'extension'         => '.html.php',
        'data'              => [],
        'context'           => null
    ];


    //
    // Construct a template instance by specifing its options.
    // Only required option is 'template', defaults will be used for others.
    // Directory root is set to $_SERVER['DOCUMENT_ROOT'], 
    // but can be overriden from the passsed options.
    //
    public function __construct($options = []) {
        $this->options['root_dir'] = $_SERVER['DOCUMENT_ROOT'];
        $this->options = array_merge($this->options, $options);
    }


    //
    // Find and render a template file with optional layout.
    // If layout is specified, template will be inserted inside it.
    //
    // Each key/value pair inside `data` array/object will become local variables inside a template
    // and $this inside a template will refer to the passed `context` object.
    //
    public function find_and_render($template_name, $options = []) {
        $options = array_merge($this->options, $options);
        $template_path = $this->template_path($template_name, $options);
        
        if ($options['layout']) {
            $layout_path = $this->layout_path($options['layout'], $options);
            $layout = $this->render($layout_path, $options['data'], $options['context']);
        }

        $template = $this->render($template_path, $options['data'], $options['context']);
        $content  = $layout ? str_replace('{{yield}}', $template, $layout) : $template;
        
        return $content;
    }


    //
    // Render a template and return the resulting content.
    //
    // $data is an associative array whose keys/values will be local variables inside a template.
    // $context is an object which becomes $this inside a template file.
    //
    public function render($file_path, $data = [], $context = null) {
        $f = $this->compile($file_path);

        if($context) {
            $f = $f->bindTo($context);
        }

        $content = $f($data);

        return $content;
    }


    //
    // Create a anonymouse function that wraps-over a template file.
    // This will allow us to specify template data and context later.
    //
    public function compile($file_path) {
        if(file_exists($file_path)) {
            return function($data) use (&$file_path) {
                extract($data, EXTR_SKIP);
                ob_start();
                include $file_path;
                return ob_get_clean();
            };   
        } else {
            throw new InvalidArgumentException('File ' . $file_path . ' does not exists.');
        }
    }


    //
    // Determine a template path (Inspired by: http://guides.rubyonrails.org/layouts_and_rendering.html)
    // There are only 3 types of paths used:
    // 1. An absolute path.
    // 2. A path relative to the templates directory.
    // 3. A path relative to a sub directory inside the templates direactory.
    //
    private function template_path($path, $options) {
        $templates_root_directory = $options['root_dir'] . $options['templates_dir'] . '/';
        $path = $this->append_extension($path, $options);

        // If a path starts with a slash `/`, we assume it's an absolute path.
        if (preg_match('/^\//', $path)) {
            return $path;
        }

        // If a path doesn't include a slash `/` at all and the `templates_sub_dir`
        // option is specified, we assume it's a relative path to that sub-directory.
        else if ($options['templates_sub_dir'] && !preg_match('/\//', $path)) {
            return $templates_root_directory . $options['templates_sub_dir'] . '/' . $path;
        }

        // For everything else, including paths that include a slash `/` inside it,
        // we assume it's a relative path to the templates directory.
        else {
            return $templates_root_directory . $path;
        }
    }


    //
    // Determine a layout path.
    //
    private function layout_path($path, $options) {
        $layouts_root_directory = $options['root_dir'] . $options['layouts_dir'] . '/';
        $path = $this->append_extension($path, $options);
        return $layouts_root_directory . $path;
    }


    //
    // Append extension to a path only if it doesn't already have one.
    //
    private function append_extension($path, $options) {
        $path_contains_extension = strpos($path, $options['extension']) !== false;
        return $path_contains_extension ? $path : $path . $options['extension'];
    }

}





//
// MarleyContext is an object that is shared 
// between a controller/callback and a template.
//
// All helper methods, shared objects, plugins and etc.
// are attached to this object.
//

class MarleyContext {

    //
    // Default options of a context object.
    //
    private $options;


    //
    // Data object whose properties become local variables inside a template.
    //
    public $data;


    //
    // Construct a context object with optional template options.
    //
    public function __construct($options = []) {
        $this->options = $options;
        $this->data = new stdClass();
    }


    //
    // Render (print) a template.
    //
    // $params may be a template name or options array.
    // If it's an array, the $render_options array is ignored.
    //
    public function render($params, $render_options = []) {
        if (is_array($params)) {
            $options = array_merge($this->options, $params);
        } else if (is_string($params)) {
            $template_neme = $params;
            $options = array_merge($this->options, $render_options);
        } else {
            throw new InvalidArgumentException('render() function\'s parameter should be an array or a string.');
        }
        
        $options['context']  = $options['context']  ?: $this;
        $options['status']   = $options['status']   ?: 200;

        if ($options['json']) {
            header('Content-type: application/json');
            print json_encode($options['json'], JSON_PRETTY_PRINT);

        } else if ($options['js']) {
            header('Content-type: text/javascript', true, $options['status']);
            print $options['js'];
            
        } else if ($options['plain']) {
            header('Content-type: text/plain', true, $options['status']);
            print $options['plain'];

        } else if ($options['html']) {
            header('Content-type: text/html', true, $options['status']);
            print $options['html'];

        } else if ($options['partial']) {
            $options['layout'] = $options['layout'] ?: false;
            $options['continue'] = isset($options['continue']) ? $options['continue'] : true;

            $template = new MarleyTemplate($options);
            $html = $template->find_and_render($options['partial']);
            print $html;

        } else {
            header('Content-type: text/html', true, $options['status']);
            $options['data'] = $options['data'] ?: (array)$this->data;
            
            $template = new MarleyTemplate($options);
            $html = $template->find_and_render($options['template'] ?: $template_neme);
            print $html;
        }

        if (!$options['continue']) {
            exit;
        }
    }


    //
    // Get the base url of the website including subdomain and port number.
    //
    private function base_url() {
        $protocol = strpos(getenv('SERVER_PROTOCOL'), 'HTTPS') !== false ? 'https' : 'http';
        $base_url = $protocol . '://' . getenv('HTTP_HOST');
        return $this->options['base_url'] ?: $base_url;
    }


    //
    // Create an url with passed route, based on the site's base url. 
    //
    public function url($path = '') {
        $does_not_needs_slash = ($path === '' || strpos($path, '/') === 0);
        return $this->base_url() . ($does_not_needs_slash ? $path : '/' . $path);
    }


    //
    // Redirect using HTTP header.
    //
    function redirect($path = '', $time = 0) {
        if($time > 0) {
            header('refresh:' . $time . ';url=' . $this->url($path));
        } else {
            header('Location: ' . $this->url($path), true);
            exit;
        }
    }


    //
    // Call a dynamically assigned function.
    // This is a workaround, because PHP's interpreter can't call
    // anonymouse functions assigned as object's properties.
    //
    public function __call($name, $arguments) {
        if ($this->$name && is_callable($this->$name)) {
            call_user_func_array($this->$name, $arguments);
        };
    }

}
