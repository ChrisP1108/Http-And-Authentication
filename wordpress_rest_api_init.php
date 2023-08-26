<?php

    class WP_Register_Rest_API_Route {

        // All Routes registered through this are at {domainurl}/wp-json/api/{route}

        private $method;
        private $url;
        private $route;
        private $headers;
        private $api_key;
        private $permission_callback;

        // Make Request

        public function initialize($request) {

            // Initialize headers variable

            $headers = [];

            // Check if there are headers passed in on the backend

            if ($this->headers) {
				forEach($this->headers as $head_name => $head_value) {
                	$headers[$head_name] = $head_value;
				}
            }

            // Add headers from the frontend

            $req_headers = $request->get_headers();

            if (!empty($req_headers)) {
                forEach($req_headers as $head_name => $head_value) {
                    if ($head_name !== "host") {
                        $headers[$head_name] = $head_value[0];
                    }
                }
            }

            // Get URL parameters

            $params = $request->get_params();

            $delete_id = '';

            // If method is a DELETE Request, add id to url prior to adding parameters

            foreach($params as $param_name => $param_value) {
                if ($param_name === 'delete_id') {
                    $delete_id .= $param_value;
                }
            }

            // Parse URL parameters into string if parameters found

            $params_string = '';

            if (!empty($params)) {

                foreach($params as $param_name => $param_value) {

                    if (!empty($params_string)) {
                        $params_string .= '&';
                    }

                    // Check for parameter 'require_api_key' and its value to see where it needs to be inserted.  Either in the url parameter or header

                    if ($param_name === 'require_api_key_parameter' || $param_name === 'require_api_key_header') {
                        if ($param_name === 'require_api_key_parameter') {
                            $params_string .= $param_value . '=' . $this->api_key;
                        }
                        if ($param_name === 'require_api_key_header') {
                            $headers[$param_value] = $this->api_key;
                        }

                    } else if ($param_name !== 'delete_id') {
                        $params_string .= $param_name . '=' . urlencode($param_value);
                    }
                }
            }

            // Add stringified URL parameters into route

            $request_with_params = $this->url . $delete_id . '?' . $params_string;

            // Parse Request Body

            $body_params = $request->get_body_params();
            
            // Make HTTP request to url

            $response = null;

            switch($this->method) {
                case 'GET':
                    $response = wp_safe_remote_get($request_with_params, ['headers' => $headers]);
                    break;
                case 'POST':
                    $response = wp_remote_post($request_with_params, ['headers' => $headers, 'body' => $body_params]);
                    break;
                case 'PUT':
                    $response = wp_remote_request($request_with_params, ['method' => 'PUT', 'headers' => $headers, 'body' => $body_params]);
                    break;
                case 'DELETE':
                    $response = wp_remote_request($request_with_params, ['method' => 'DELETE', 'headers' => $headers]);
                    break;
            }

            // Check for a valid response

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) > 201) {
                $res = $response['response'];
                return new WP_Error('api_error', $res['message'], array('status' => $res['code']));
            }

            // Get the response body

            $data = wp_remote_retrieve_body($response);

            // Decode the JSON data

            $decoded_data = json_decode($data);

            return rest_ensure_response($decoded_data);
        }

        // Check that request is coming from same domain to prevent external URL usage

        public function permissions_check($request) {

            if ($this->domain_restricted === true) {

                $referer = $request->get_header('referer');
            
                $allowed_domain = parse_url(get_site_url(), PHP_URL_HOST);
                $referer_domain = parse_url($referer, PHP_URL_HOST);
            
                if ($referer_domain === $allowed_domain) {
                    return true;
                } else {
                    return new WP_Error('domain_not_allowed', 'Access from external domains is not allowed.', array('status' => 403));
                }
            } 
            if ($this->permission_callback !== null && is_callable($this->permission_callback)) {
                return call_user_func($this->permission_callback, $request);
            } else return true;
        }

        // Register route with Wordpress REST API

        public function wp_rest_api_register_route() {
            register_rest_route('api', $this->route, [
                'methods' => $this->method,
                'permission_callback' => [$this, 'permissions_check'],
                'callback' => [$this, 'initialize'],
            ]);
        }

        // Check that request method is valid.  If not, set to GET by default

        private function method_parse($method) {

            $req_method = strtoupper($method);
            
            switch($req_method) {
                case 'GET':
                    return 'GET';
                case 'POST':
                    return 'POST';
                case 'PUT':
                    return 'PUT';
                case 'DELETE':
                    return 'DELETE';
                default:
                    return 'GET';
            }
        }

        // Constructor.  Runs when class is instantiated.

        public function __construct($parameters) {
            $this->method = $this->method_parse($parameters['method'] ?? null);
            $this->url = strtolower($parameters['url']) ?? null;
            $this->route = strtolower($parameters['route']) ?? null;
            $this->headers = $parameters['headers'] ?? null;
            $this->api_key = $parameters['api_key'] ?? null;
            $this->domain_restricted = $parameters['domain_restricted'] ?? false;
            $this->permission_callback = $parameters['permission_callback'] ?? null;

            if ($this->url && $this->route) {
                add_action('rest_api_init', [$this, 'wp_rest_api_register_route']);
            } else {
                echo 'Error registering REST API route. Check that the instantiation of the "WP_Register_Rest_API_Route" class has a url and route parameters passed into it at a minimum.';
            }
        }
    }

    // TESTER API KEY

    // define("TESTER_API_KEY", "abe91hg62gf82");	

    // new WP_Register_Rest_API_Route([
    //     'method' => 'GET',
    //     'url' => 'https://echo.zuplo.io/',
    //     'route' => 'tester',
    //     'headers' => ['test_head' => 'test_value'],
    //     'api_key' => TESTER_API_KEY,
    //     'domain_restricted' => false,
    //     'permission_callback' =>  function($request) {
    //         return true;
    //     }
    // ]);
