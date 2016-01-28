<?php
include 'db.php';
require 'Slim/Slim.php';

$speakapSignedRequest = new Speakap\SDK\SignedRequest(
    '16763705810009f4', // The app identifier 16763b2b4c0008f8
    'd5f5da18458ecfb19f22386bb5c681507695958858ca8bd66dad9cc1e2a1e208'   // The secret, something you should never actually commit in any repository :-) 11aa5dc984cd178dc5ddc411edd24d3ccdc3951256bd7a58f4b1915e8ec90853
);
if (!$speakapSignedRequest->validateSignature($_POST)) {
    die(
        "I'm sorry, but the request seems invalid. Please try again!" .
        "Note that this application can only be started from within Speakap."
    );
}

$app = new \Slim\Slim();

$app->get('/users', 'getUsers');

$app->run();


function getUsers()
{
    //query naar speakap
    $netID = "15321edfd8000c68";
    $tokensecret = "166ab7efb600018c_5f2b2a04f3722ae8c15399b83bd70847c426e8158ee9bac78ec28b0d13e160e7";
    $service_url = "https://api.speakap.io/networks/" . $netID . "/users/ -H \"Authorization: Bearer " . $tokensecret . "\"";
    $curl = curl_init($service_url);
    $curl_response = curl_exec($curl);

    //Do something with response
    echo("<script>console.log('response: )" . implode($curl_response) . "');</script>");

    $hostname = 'wide.synology.me';
    $username = "pantera";
    $password = "pantsy123";
    try {
        $dbh = new PDO("mysql:host=$hostname;dbname=speakapdump", $username, $password);
        $error_array = array();

        echo("<script>console.log('connected to db');</script>");

        $response_decoded = json_decode($curl_response, true);
        foreach($response_decoded as $users)
        {
            $_username = $users['name'];
            $stmt = $dbh -> prepare("INSERT into speakapusers('EID', 'email', 'firstname', 'lastname', 'birthday', 'imageurl', 'tel')
                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($users['EID'],$users['primaryEmail'],$_username['firstName'],$_username['lastName'],$users['birthday'],$users['avatarThumbnailUrl'].'/profile-image'));
            $result = $stmt->rowCount();
            if($result < 1){
                echo("<script>console.log('User insert failed for the following user: '+".$users['EID'].");</script>");
                //TODO log all failed EID's to separate logfile
                $error_array[] = $users['EID'];
                $error = true;
            }
        }
    } catch (PDOException $e) {
        echo("<script>console.log(".$e->getMessage().");</script>");
        $error = $e->getMessage();
    }
}

if (isset($_GET['clicked'])) {
    getUsers();
}
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
    <a href="index.php?clicked=true"">Sync data</a>
    <div class="errormsg">
        <?php
        if (isset($error)) {
            echo("<p>Something went wrong while connecting to the database. Please contact support.</p>");
            echo("<div>");
            if(isset($error_array)){for($i = 0; $i < count($error_array); $i++){
                echo("The following user was not succesfully added: ".$error_array[$i]);
            }}
            echo("</div>");
        }
        ?>
    </div>
</div>
</body>
</html>
