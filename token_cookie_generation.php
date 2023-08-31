<?php  
    class Token_Handler {

        // Web Token Secret

        private static $web_token_secret = '2814A5@nzPWv&9=`g301v8s5E&sz]=|aV^GWja45-9!$xB<f%cA#xTn@GG#39024';

        // Expiration time after current time in seconds

        private static $expiration_default = 86400;

        public static function pre_hash_stringify($expiration = null) {
            if (!$expiration) {
                throw new Exception("Expiration time required.");
            } else return base64_encode($_SERVER['HTTP_USER_AGENT']). self::$web_token_secret  . base64_encode($expiration);
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
                'cost' => 8,
            ];  

            // Bcrypt Hashing

            $hash = password_hash(self::pre_hash_stringify($expiration_string), PASSWORD_BCRYPT, $options);

            // Compile Token

            $token = json_encode([
                'id' => $user_id,
                'expiration' => $expiration_string,
                'signature' => $hash
            ]);

            return base64_encode($token);
        }

        public static function validate($cookie = null, $users = null) {

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
                if ($u['id'] == $decoded_id) {
                    $user = $u;
                }
            }

            // Check if user found.  Otherwise return false
            
            if ($user) {
                $user_created = strval($user['created']);
                $expiration_time = strval($decoded['expiration']) ?? '0';

                if (intval($expiration_time) <= time()) {
                    echo 'Token Expired';
                    return false;
                }

                $hashed_signature = $decoded['signature'];
                $check = password_verify(self::pre_hash_stringify($expiration_time), $hashed_signature);
                
                return $check;
            
            } else return false;
        }

        public static function set_cookie($data = null) {
            if (!$data || !$data['name'] || !$data['value']) {
                throw new Exception("Cookie name and value parameters required.");
            }
            setcookie($data['name'], $data['value'], $data['expiration'] ?? self::$expiration_default, '/', '', false, $data['http_only'] ?? false);
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

    // function set_cookie($data) {
    //     setcookie($data['name'], $data['value'], $data['expiration'] ?? time() + 86400, '/', '', false, $data['http_only'] ?? false);
    // }

    // $cookie_name = "Token";

    // $cookie = $_COOKIE[$cookie_name];

    // $token_value = Token_Handler::generate($users[0]['id']);

    // if (!isset($cookie)) {
    //     Token_Handler::set_cookie ([
    //         'name' => $cookie_name,
    //         'value' => $token_value,
    //         'expiration' => time() + 86400,
    //         'http_only' => true
    //     ]);
    //     echo '<h1>Cookie set. Hit refresh to test validation.</h1>';
    // } else {
    //     if (Token_Handler::validate($cookie, $users)) {
    //         echo '<h1>Token valid.</h1>';
    //     } else {
    //         echo '<h1 style="color: red;">Cookie token not valid. Cookie has been removed.</h1>';
    //     }
    // }