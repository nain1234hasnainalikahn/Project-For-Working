<?php
session_start(); // Don't forget the semicolon

// FaucetPay API Configuration
$sitekey = '6a75bac3-bdf5-43c2-a56f-1da35e0c230f';
$secretkey = '0x405dF20A566e06AF36Fd77161B037dA4026Cb74A';
$apiKey = '39255a79df0997fd9f96853ca2d7c0e5b79a8e290fab69255cf8f93f4027cfec';

// Messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : "";
$errorMessages = isset($_SESSION['error']) ? $_SESSION['error'] : "";

// Function to set the BTC address in a cookie
function setBTCAddressInCookie($address) {
    setcookie('Btc_Address', $address, time() + 30 * 24 * 60 * 60); // Store for 30 days
}

// Function to retrieve the BTC address from the cookie
function getBTCAddressFromCookie() {
    if (isset($_COOKIE['Btc_Address'])) {
        return $_COOKIE['Btc_Address'];
    }
    return "";
}

function validateBTCAddress($address)
{
    $pattern = '/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,39}$/';
    return preg_match($pattern, $address);
}

function getFaucetPayBalance()
{
    global $apiKey;
    $apiUrl = 'https://faucetpay.io/api/v1/balance';

    $params = array(
        'api_key' => $apiKey,
        'currency' => 'BTC',
        'address' => '17Wp5GgKGSwHEZmhAzTgeKXesVExgQVxRS',
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($responseData && $responseData['status'] == 200) {
        return $responseData['balance'];
    } else {
        return 0; // Return 0 balance if there's an error
    }
}

$faucetPayBalance = getFaucetPayBalance();

if (isset($_POST["submit"])) {
    // Get user input
    $recipientAddress = $_POST['address'];
    $response = $_POST['h-captcha-response'];

    // Check if the FaucetPay BTC address is provided
    if (empty($recipientAddress)) {
        $_SESSION['error'] = "Error: FaucetPay BTC address is required.";
    } elseif (empty($response)) {
        $_SESSION['error'] = "Error: Please solve the hCaptcha.";
    } elseif (validateBTCAddress($recipientAddress)) {
        // Verify hCaptcha
        $verifyUrl = 'https://hcaptcha.com/siteverify';
        $data = array(
            'secret' => $secretkey,
            'response' => $response
        );

        $options = array(
            'http' => array(
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );

        $context = stream_context_create($options);
        $verify = file_get_contents($verifyUrl, false, $context);
        $captchaSuccess = json_decode($verify);

        if ($captchaSuccess->success) {
            if (isset($_SESSION['timer_start'])) {
                $timer_duration = 600; // 10 minutes
                $current_time = time();
                $timer_start = $_SESSION['timer_start'];
            
                if (($current_time - $timer_start) < $timer_duration) {
                    $_SESSION['error'] = "Please wait until the timer expires before claiming again.";
                    header("Location:  index.php");
                    exit;
                } else {
                    unset($_SESSION['timer_start']);
                    unset($_SESSION['error']);
                    unset($_SESSION['success']);
                }
            }

            // Your FaucetPay API key
            $api_key = '39255a79df0997fd9f96853ca2d7c0e5b79a8e290fab69255cf8f93f4027cfec';
            // Generate a random amount between 1 and 6 satoshis
            $amount = rand(1, 6);

            // Send satoshis via FaucetPay API
            $apiUrl = "https://faucetpay.io/api/v1/send";
            $params = array(
                "api_key" => $api_key,
                "to" => $recipientAddress,
                "currency" => "BTC",
                "amount" => $amount
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);
            
            if ($responseData && $responseData["status"] == 200) {
                $_SESSION['success'] = "Congratulations! You received $amount satoshis.";
                // Start the timer when the user successfully claims a reward
                setBTCAddressInCookie($recipientAddress);
                $_SESSION['timer_start'] = time();
            } else {
                $_SESSION['error'] = "Failed to send satoshis. Error: " . $responseData["message"];
            }
          
            header("Location: index.php"); // Redirect to your page after claiming
            exit;
        } else {
            $_SESSION['error'] = "Invalid hCaptcha.";
        }
       
    } else {
        $_SESSION['error'] = "Invalid BTC address format.";
    }
    header("Location: index.php");
    exit;
}
$storedBTCAddress = getBTCAddressFromCookie();
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title id="timer">H-FAUCET | BTC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    <style>
        body {
            margin:0;
            padding:0;
            background-image: url(sports.jpg);
            background-repeat: no-repeat;
            background-size: cover;
            background-position:center;
            background-attachment:fixed;
            height:100%;
            width: 100%;

        }

        @media (max-width: 767px) {
            .left-ad {
                display: none;
            }

            .bottom-ad {
                display: none;
            }

            .container-fluid {
                padding-left: 15px;
                padding-right: 15px;
            }

            .Claim_10Minute {
                width: 100%;
                max-width: 100%;
                padding-left: 10px;
                padding-right: 10px;
                margin-left: auto;
                margin-right: auto;
                text-align: center;

            }

            #referralInput {
                width: 100%;
                max-width: 100%;
                margin-left: auto;
                margin-right: auto;
                text-align: center;
            }
            #AMT{
                display:none;
                padding-right: 20px;
            }
        }
        .success-message {
            color: white;
            background:green;
            padding:10px;
            border-radius:10px;
        }

        .error-message {
            color: white;
            font-weight:bold;
            background:red;
            padding:10px;
            border-radius:10px;
        }

        #timerText {
            color: white;
            font-size:20px;
            background:orange;
            padding:20px;
            
        }
    </style>


</head>

<body>
    <nav class="navbar navbar-expand navbar-light fixed-top" style="background-color:#e9ebea; padding: 15px; ">
        <a class="navbar-brand" href="#" style="font-weight: bold; font-size: 21px; padding-left: 25px;">
            <span style="color: rgb(255, 0, 0);"><i class="fa-sharp fa-solid fa-heart" style="color: #e70835;"></i> H</span>-Faucet | <span style="color: orange;">Btc</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav mr-auto"></ul>
            <ul class="nav justify-content-end">
            <li class="nav-item">
        <a class="nav-link" href="#" style="color: White; background-color: orange; font-weight: liter;  border:2px solid white;">
            <i class="fa-solid fa-gift"></i><span id="AMT">Amount | </span><span class="BTC-Color" style="color: black; font-weight:light;">
                <?php echo $faucetPayBalance; ?> satoshi
            </span>
        </a>
    </li>
            </ul>
        </div>
    </nav>

    <!--Left and Right Advertisement Banner-->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12" style="text-align: center; width: auto; height: auto; padding-top: 80px;">
                Top Ad
            </div>
            <div class="col-12" style="text-align: center; width: auto; height: auto; padding-top: 30px;">
                Top Ad
            </div>
        </div>
        <br>
        <br>
        <div class="row">
            <div class="col-4 left-ad" style="text-align: left;">
                left
            </div>
            <div class="col-12 col-lg-4" style="text-align: center; ">
            <p id="successMessage" style="color:Green; padding:5px; font-weight:bolder;"><?php echo $successMessage; ?></p>
            <p id="errorMessage" style="color:red; padding:5px;"><?php echo $errorMessages; ?></p>
            <p class="Claim_10Minute" style="background-color: #fdba02; padding: 14px; color: whitesmoke; font-weight: bold;">
                    Claim 1 to 10 Satoshi in Every 10 Minute
                </p>
                <p class="Claim_10Minute" style="background-color: #f54d77; border:2px dotted white; padding: 14px; color: whitesmoke; font-weight: bold;">
                    Faucet Requires a <a href="#">Faucetpay</a> Btc Address
                </p>
                <!--Form For Payment Input and Referral Link-->
              <form method="post" class="form-center">
                   <!-- Form For Payment Input and Referral Link -->
                   <form method="post" class="form-center" id="form">
                <div class="input-group mb-6">
  <div class="input-group-prepend">
    <span class="input-group-text" id="basic-addon1" style="border-radius:0px; background:red; color:white; border:red 2px solid; font-size:20px;"><i class="fa-brands fa-btc"></i></span>
  </div>
  <input type="text" class="form-control" name="address" id="btcAddress" oninput="updateReferralLink()" placeholder="Enter Faucetpay BTC Address" style="text-align: left; border: 2px solid red; padding:20px; border-radius:0px; outline:none; background-color: none; color: white; font-weight: lighter; background: none;" value="<?php echo $storedBTCAddress; ?>" required>
</div>
                        <?php                 
// Check if the timer is active
if (isset($_SESSION['timer_start'])) {
    // Calculate remaining time
    $timer_duration = 600; // 10 minutes in seconds
    $current_time = time();
    $timer_start = $_SESSION['timer_start'];

    if (($current_time - $timer_start) < $timer_duration) {
        $remaining_time = $timer_duration - ($current_time - $timer_start);
        $minutes = floor($remaining_time / 60);
        if ($minutes > 0) {
            echo '<div id="timer" style="color:white; background:red; font-weight:lighter; padding:10px; margin-top:-10%; position:relative;  cursor: pointer;">You Can Claim Again in: ' . $minutes . ' minutes </div>';
        }
    } else {
        // Timer has completed, remove messages
        unset($_SESSION['timer_start']);
        unset($_SESSION['error']);
        unset($_SESSION['success']);
    }
}
?>
    <div class="row">
        <div class="col-6">
            Cntdc
        </div>
        <div class="col-6">
            saskbc
        </div>
    </div>

    <button type="button" id="btn_popup2" class="btn btn-outline-primary btn-block" data-toggle="modal" data-target="#claimModal">Claim Now
      <i class="fa-solid fa-circle-arrow-right" style="color: #04ff00;"></i>
    </button>

    <!-- Modal of Captcha and Advertisement -->
    <div class="modal fade" id="claimModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" style="font-weight: bold;"><span style="color: rgb(255, 0, 0);">H</span>-Faucet |
                        <span style="color: orange;">Captcha</span> </h3>
                    <button type="button" class="close" data-dismiss="modal"><span
                            style="color: rgb(0, 255, 85); font-weight: bold;">&times;</span></button>
                </div>
                <div class="modal-body" style="text-align: center;">
                    <div>
                        <center>
                            TOP ADS
                        </center>
                    </div>
                    <div id="hCaptcha" class="captcha-container">
                        <!-- Add the hCaptcha widget here -->
                        <div class="h-captcha" data-sitekey="<?php echo $sitekey; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            Ad Bottom
                        </div>
                        <div class="col-6">
                            Ad Bottom
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="submit" class="btn btn-success"><i class="fa-solid fa-gift"></i> Claim Reward</button>
                </div>
            </div>
        </div>
    </div>

    <hr>
</form>

            </div>
            <div class="col-4 bottom-ad" style="text-align: right;">
                Bottom
            </div>
               <!-- Referral section -->
    <div style="background-color: rgb(235, 68, 68); width: 100%; padding: 80px 40px; text-align: center; font-weight: lighter; font-size: 25px;">
        <i class="fa-solid fa-gear" style="color: #fff; font-size: 27px;"></i>
        <br>
        <br>
        <h4 style="color: #fff; font-size: 25px; font-weight: bold;">Earn 30% By Referral</h4>
        <br>
        <div class="referral-section">
           
            <input type="text" class="form-control" id="referralCode" readonly value="/ref/your_address" style="text-align: center; padding: 0px 30px; cursor:pointer;" />
        </div>
    </div>
            <div class="container-fluid" style="text-align: center; font-weight: lighter; color: #000; padding: 25px; background-color: rgb(189, 231, 217);">
                <h2>H - Faucet</h2>
                <hr>
                <p>
                    <i>A faucet is a website or application that distributes small amounts of cryptocurrency, such as Bitcoin (BTC), to users for free. In the context of Bitcoin, these small amounts are called Satoshis. These actions can include solving captchas, viewing advertisements, or completing simple tasks. While the amount of Bitcoin received from a faucet is very small (often ranging from a few Satoshis to a few hundred Satoshis), over time, users can accumulate more Satoshis by consistently using multiple faucets. Faucets serve as a means for people to learn about Bitcoin and experiment with it without investing any money.</i>
                </p>
            </div>
            <div class="jumbotron bg-info" style="width: 100%; text-align: center; margin: 0; border-radius: 0;">
                <h3 style="color: #fff; font-weight: bold;">SPECIAL OFFER</h3>
                <p>If you are interested in purchasing this script, please contact us on Telegram.</p>
            </div>
            <div class="container-fluid" style="background-color: #1078a1;text-align: center;">
                <img src="" alt="">
                <hr>
                <div>
                 
                        <h4 style="text-align: center; color: #fff;">GET IN TOUCH</h5>
                        <div class="social" style="padding: 10px;">
                            <a href="#" style="padding: 10px; color: #0698fa; font-size: 25px;"><i class="fa-brands fa-facebook"></i></a>
                            <a href="#" style="padding: 10px; color: #00acee; font-size: 25px;"><i class="fa-brands fa-square-twitter"></i></a>
                            <a href="#" style="padding: 10px; color: #229ED9; font-size: 25px;"><i class="fa-brands fa-telegram"></i></a>
                        </div>
                </div>
                <hr>
                <p style="text-align: center; background-color: #c7c7c6; width: 100%; padding: 25px; color: white;">
                    <i>&copy; <?php echo date("Y"); ?> https://h-faucetbtc.com/ | BTC .All Right Reserved.</i>
                </p>
            </div>
        </div>
    </div>
    <script>
        // Get the input field and referral code element
        var addressInput = document.getElementById('address');
        var referralCodeInput = document.getElementById('referralCode');
        
        // Add an event listener to the address input field
        addressInput.addEventListener('input', generateReferralCode);
        
        // Function to generate the referral code
        function generateReferralCode() {
            var address = addressInput.value;
            var referralCode = 'https://h-faucetbtc.000webhostapp.com//ref/' + address;
            referralCodeInput.value = referralCode;
        }
    </script>
 
    <!-- JavaScript Scripts -->
    <script>
        // Get the input field and referral code element
        var addressInput = document.getElementById('btcAddress');
        var referralCodeInput = document.getElementById('referralCode');

        // Add an event listener to the address input field
        addressInput.addEventListener('input', generateReferralCode);

        // Function to generate the referral code
        function generateReferralCode() {
            var address = addressInput.value;
            var referralCode = 'https://h-faucetbtc.000webhostapp.com//ref/' + address;
            referralCodeInput.value = referralCode;
        }
    </script>
   <script>
    // Function to refresh the timer every minute
    function refreshTimer() {
        const timerElement = document.getElementById("timer");
        if (timerElement) {
            location.reload(); // Reload the page to refresh the timer
        }
    }

    // Refresh the timer every minute
    setInterval(refreshTimer, 60000); // 60000 milliseconds = 1 minute
</script> 
<script>
// Function to retrieve and display the BTC address from the cookie
function getBTCAddressFromCookie() {
    const addressCookie = document.cookie.match(/Btc_Address=([^;]+)/);
    if (addressCookie && !document.getElementById('btcAddress').value) {
        document.getElementById('btcAddress').value = addressCookie[1];
    }
}
</script>
<script>
// Function to hide success and error messages after 10 seconds
function hideMessages() {
    const successMessage = document.getElementById("successMessage");
    const errorMessage = document.getElementById("errorMessage");

    if (successMessage) {
        successMessage.style.display = "none";
    }
    if (errorMessage) {
        errorMessage.style.display = "none";
    }
}

// Hide messages after 10 seconds (10000 milliseconds)
setTimeout(hideMessages, 30000);
</script>
</body>
</html>