<?php

    class WP_Register_Rest_Get_Route {

        // All GET Routes registered through this are at {domainurl}/wp-json/api/{route}

        private $url;
        private $route;
        private $token;

        // Make GET Request

        function get_data() {

            // Setup headers for JSON

            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Check if there is a token and add it to headers

            if ($this->token) {
                $headers['Authorization'] = 'Bearer ' . $this->token;
            }
            
            // Make GET Request

            $response = wp_safe_remote_get($this->url, ['headers' => $headers]);

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

        function __construct($url = null, $route = null, $token = null) {
            $this->url = $url;
            $this->route = $route;
            $this->token = $token;

            if ($this->route && $this->url) {
                add_action('rest_api_init', [$this, 'wp_rest_api_register_route']);
            } else {
                echo 'Error registering REST API GET route. Check that the instantiation of the "WP_Register_Rest_Get_Route" class has a url and route parameters passed into it.';
            }
        }
    }

    // POST API KEY

    // define("POST_API_TOKEN", "abe91hg62gf82");	

    // $post_data = new WP_Register_Rest_Get_Route("https://jsonplaceholder.typicode.com/posts", "posts", POST_API_TOKEN);
