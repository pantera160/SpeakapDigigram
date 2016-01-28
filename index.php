<?php

require 'vendor/autoload.php';

$speakapSignedRequest = new Speakap\SDK\SignedRequest(
    '1694201e27000230', // The app identifier 16763b2b4c0008f8
    '82d4b328056730e6cafda841a12575fd710c1ece633939fa81d87931d6e9978a'   // The secret, something you should never actually commit in any repository :-) 11aa5dc984cd178dc5ddc411edd24d3ccdc3951256bd7a58f4b1915e8ec90853
);
if (!$speakapSignedRequest->validateSignature($_POST)) {
    die(
        "I'm sorry, but the request seems invalid. Please try again!" .
        "Note that this application can only be started from within Speakap."
    );
}

function getUsers()
{
    //query naar speakap
    $netID = "1694201e27000230";
    $tokensecret = "1693fe55b20007e4_82d4b328056730e6cafda841a12575fd710c1ece633939fa81d87931d6e9978a";
    $service_url = "https://api.speakap.io/networks/" . $netID . "/ -H \"Authorization: Bearer " . $tokensecret . "\"";
    $curl = curl_init($service_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    $curl_response = curl_exec($curl);
    error_log($curl_response);

    //Do something with response
    echo("<script>console.log('response: " . implode($curl_response) . "');</script>");

    $hostname = 'wide.synology.me';
    $username = "pantera";
    $password = "pantsy123";
    try {
        $dbh = new PDO("mysql:host=$hostname;dbname=speakapdump", $username, $password);
        $error_array = array();

        echo("<script>console.log('connected to db');</script>");

        $response_decoded = json_decode($curl_response, true);
        foreach ($response_decoded as $users) {
            $_username = $users['name'];
            $_tels = $users['telephoneNumbers'];
            $_primaryTel = '';
            for ($i = 0; $i < count($_tels); $i++) {
                if ($_tels[i]['label'] == 'Primary') {
                    $_primaryTel = $_tels[i]['value'];
                    break;
                }
            }
            $stmt = $dbh->prepare("INSERT into speakapusers('EID', 'email', 'firstname', 'lastname', 'birthday', 'imageurl', 'tel')
                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($users['EID'], $users['primaryEmail'], $_username['firstName'], $_username['lastName'], $users['birthday'], $users['avatarThumbnailUrl'] . '/profile-image', $_primaryTel));
            $result = $stmt->rowCount();
            if ($result < 1) {
                echo("<script>console.log('User insert failed for the following user: '+" . $users['EID'] . ");</script>");
                //TODO log all failed EID's to separate logfile
                $error_array[] = $users['EID'];
                $error = true;
            }
        }
        $succes = true;
    } catch (PDOException $e) {
        echo("<script>console.log(" . $e->getMessage() . ");</script>");
        $error = $e->getMessage();
    }
}

getUsers();
?>
<!doctype html>
<html>
<head>
    <script type="text/javascript">
        var Speakap = {

            // The app identifier, note that this identifier is case-sensitive.
            appId: "166ab7efb600018c",

            // Sign our payload using our own secret and define it for the Speakap proxy.
            signedRequest: "<?php echo $speakapSignedRequest->getSignedRequest($_POST); ?>"
        };

    </script>
</head>
<body>
<div>
    <p>Getting data from speakap, errors will be shown below.</p>

    <div class="errormsg">
        <?php
        if (isset($error)) {
            echo("<p>Something went wrong while connecting to the database. Please contact support.</p>");
            echo("<div>");
            if (isset($error_array)) {
                for ($i = 0; $i < count($error_array); $i++) {
                    echo("The following user was not succesfully added: " . $error_array[$i]);
                }
            }
            echo("</div>");
        }
        ?>
    </div>
    <div class="succesmsg">
        <?php
        if (isset($succes)) {
            echo("<p>Data succesfully send to db.</p>");
        }
        ?>
    </div>
</div>
</body>
</html>
