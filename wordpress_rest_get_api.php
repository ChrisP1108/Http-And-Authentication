<?php

    class WP_Register_Rest_Get_Route {

        // All GET Routes registered through this are at {domainurl}/wp-json/api/{route}

        private $url;
        private $route;
        private $headers;
        private $api_key;

        // Make GET Request

        function get_data($request) {

            // Initialize headers variable

            $headers = [];

            // Check if there are headers passed in on the backend

            if ($this->headers) {
				forEach($this->headers as $head_name => $head_value) {
                	$headers[$head_name] = $head_value;
				}
            }

            // Get URL parameters

            $params = $request->get_params();

            // Parse URL parameters into string if parameters found

            $params_string = '';

            if (!empty($params)) {

                foreach($params as $param_name => $param_value) {

                    if (!empty($params_string)) {
                        $params_string .= '&';
                    }

                    // Check for parameter 'require_api_key' and its value to see where it needs to be inserted.  Either in the url parameter or header

                    if ($param_name === 'require_api_key_parameter') {
                        $params_string .= $param_value . '=' . $this->api_key;

                    } else if ($param_name === 'require_api_key_header') {
                        $headers[$param_value] = $this->api_key;

                    } else {
                        $params_string .= $param_name . '=' . urlencode($param_value);
                    }
                }
            }

            // Add stringified URL parameters into route

            $request_with_params = $this->url . '?' . $params_string;
            
            // Make GET Request

            $response = wp_safe_remote_get($request_with_params, ['headers' => $headers]);

            // Check for a valid response

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return new WP_Error('api_error', 'Failed to fetch data from external API.', array('status' => 500));
            }

            // Get the response body

            $data = wp_remote_retrieve_body($response);

            // Decode the JSON data

            $decoded_data = json_decode($data);

            return rest_ensure_response($decoded_data);
        }

        // Check that request is coming from same domain to prevent external URL usage

        function check_domain($request) {

            $referer = $request->get_header('referer');
        
            $allowed_domain = parse_url(get_site_url(), PHP_URL_HOST);
            $referer_domain = parse_url($referer, PHP_URL_HOST);
        
            if ($referer_domain === $allowed_domain) {
                return true;
            } else {
                return new WP_Error('domain_not_allowed', 'Access from external domains is not allowed.', array('status' => 403));
            }
        }

        // Register GET route with Wordpress REST API

        function wp_rest_api_register_route() {
            register_rest_route('api', $this->route, [
                'methods' => 'GET',
                'permission_callback' => [$this, 'check_domain'],
                'callback' => [$this, 'get_data'],
            ]);
        }

        // Constructor.  Runs when class is instantiated.

        function __construct($url = null, $route = null, $headers= null, $api_key = null) {
            $this->url = $url;
            $this->route = $route;
            $this->headers = $headers;
            $this->api_key = $api_key;

            if ($this->route && $this->url) {
                add_action('rest_api_init', [$this, 'wp_rest_api_register_route']);
            } else {
                echo 'Error registering REST API GET route. Check that the instantiation of the "WP_Register_Rest_Get_Route" class has a url and route parameters passed into it.';
            }
        }
    }

    // TESTER API KEY

    // define("TESTER_API_KEY", "abe91hg62gf82");	

    // new WP_Register_Rest_Get_Route("https://echo.zuplo.io/", "tester", ['testing' => 'tester'], TESTER_API_KEY);
