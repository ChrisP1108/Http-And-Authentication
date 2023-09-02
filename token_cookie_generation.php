<?php  
    class Token {

        // Web Token Secrets

        private static $web_token_secret_1 = '2814A5@nzPWv&9=`g301v8s5E&sz]=|aV';
        private static $web_token_secret_2 = '^GWja45-9!$xB<f%cA#xTn@GG#39024';

        // Expiration time after current time in seconds

        private static $expiration_default = 86400;

        // Hash string algorithm for token signature

        private static function pre_hash_stringify($expiration = null) {
            if (!$expiration) {
                throw new Exception("Expiration time required.");
            } else return self::$web_token_secret_1 . base64_encode($_SERVER['HTTP_USER_AGENT']) . self::$web_token_secret_2 . base64_encode($expiration);
        }

        public static function generate($id = null, $expiration = null) {

            // User Parameters

            $user_id = strval($id) ?? null;
            $expiration = $expiration ?? time() + self::$expiration_default;
            $expiration_string = strval($expiration);

            if (!$user_id) {
                throw new Exception("User Id input required");
            }

            // Hashing Salt Rounds.

            $options = [
                'cost' => 10,
            ];  

            // Bcrypt Hashing

            $hash = password_hash(self::pre_hash_stringify($expiration_string), PASSWORD_BCRYPT, $options);

            // Compile Token

            $token = json_encode([
                'id' => $user_id,
                'expiration' => $expiration_string,
                'key' => $hash
            ]);

            return base64_encode($token);
        }

        public static function cookie_valid($cookie = null, $users = null, $user_id_key = 'id') {

            if (!$cookie || !$users) {
                throw new Exception("Cookie and user data required.");
            }

            $json_token = base64_decode($cookie) ?? 'no data';

            $decoded = json_decode($json_token, true);
            
            // Find user in database based upon id in token
            
            $decoded_id = $decoded['id'] ?? null;

            if (!$decoded_id) {
                return false;
            }
            
            $user = null;
            
            foreach($users as $u) {
                if ($u[$user_id_key] == $decoded_id) {
                    $user = $u;
                }
            }

            // Check if user found.  Otherwise return false
            
            if ($user) {
                $expiration_time = strval($decoded['expiration']) ?? '0';

                // Check if token is expired

                if (intval($expiration_time) <= time()) {
                    return false;
                }

                // Verify key

                $hashed_key = $decoded['key'];
                $check = password_verify(self::pre_hash_stringify($expiration_time), $hashed_key);
                
                return $check;
            
            } else return false;
        }

        public static function set_cookie($data = null) {
            if (!$data || !$data['name'] || !$data['value']) {
                throw new Exception("Cookie name and value parameters required.");
            }
            setcookie($data['name'], $data['value'], $data['expiration'] ?? time() + self::$expiration_default, '/', '', $data['secure'] ?? false, $data['http_only'] ?? false);
        }

        public static function remove_cookie($name = null) {
            if (!$name) {
                throw new Exception("Cookie name parameter required.");
            }
            setcookie($name, '', time() - 3000);
        }

        public static function generate_set_cookie($data) {
            $data['value'] = self::generate($data['id'] ?? null, $data['expiration'] ?? null);
            self::set_cookie($data);
        }
    }

    // $users = [
    //     [
    //         'id' => 2,
    //     ],
    //     [
    //         'id' => 3,
    //     ]
    // ];

    // $cookie_name = "Token";

    // $cookie = $_COOKIE[$cookie_name];

    // $token_value = Token::generate($users[0]['id']);

    // if (!isset($cookie)) {
    //     Token::generate_set_cookie ([
    //         'id' => $users[0]['id'],
    //         'name' => $cookie_name,
    //         'expiration' => time() + 86400,
    //         'secure' => true,
    //         'http_only' => true
    //     ]);
    //     echo '<h1>Cookie set. Hit refresh to test validation.</h1>';
    // } else {
    //     if (Token::cookie_valid($cookie, $users, 'id')) {
    //         echo '<h1>Token valid.</h1>';
    //     } else {
    //         Token::remove_cookie($cookie_name);
    //         echo '<h1 style="color: red;">Cookie token not valid. Cookie has been removed.</h1>';
    //     }
    // }
