<?php

require 'vendor/autoload.php';

$speakapSignedRequest = new Speakap\SDK\SignedRequest(
    '1694f289e4000cd8', // The app identifier 16763b2b4c0008f8
    '711130129955d7c9461d7232594b3ca05964934bdf4194502b99c936df403fc9'   // The secret, something you should never actually commit in any repository :-) 11aa5dc984cd178dc5ddc411edd24d3ccdc3951256bd7a58f4b1915e8ec90853
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
    $netID = "15321edfd8000c68";
    $tokensecret = "1694f289e4000cd8_711130129955d7c9461d7232594b3ca05964934bdf4194502b99c936df403fc9";
    $service_url = "https://api.speakap.io/networks/" . $netID . "/ -H \"Authorization: Bearer " . $tokensecret . "\"";

}

?>
<!doctype html>
<html>
<head>
    <title>Snake</title>
    <script type="text/javascript">
        var Speakap = {

            // The app identifier, note that this identifier is case-sensitive.
            appId: "1694f289e4000cd8",

            // Sign our payload using our own secret and define it for the Speakap proxy.
            signedRequest: "<?php echo $speakapSignedRequest->getSignedRequest($_POST); ?>"
        };
    </script>

    <!-- Include our JavaScript -->
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/speakap.js"></script>


    <!-- Load our custom CSS -->


</head>
<body>
<div class="placeholder"></div>
<div id="snake-pit" tabindex="0"></div>

<script type="text/javascript">

    // The element we're loading content in
    var placeholderEl = $('div.placeholder');

    /**
     * Here bootstrapping begins. We'll be listening for our handshake to be complete, this is a bit similar to the
     * usual "dom ready" event. After the handshake is complete, all information you need will be available or is in
     * a state so that it can get the information.
     *
     * @see http://developer.speakap.io/portal/tutorials/frontend_proxy.html#dohandshake
     */

        // Wait until we've completed our handshake
    Speakap.doHandshake.then(function () {

        // Let's figure out who we are and show SOME information about the logged in user.
        // @see http://developer.speakap.io/portal/user.html (Note: Not everything shown here is available.)
        Speakap.getLoggedInUser().then(function (user) {

            placeholderEl.append('<div class="profile"><div class="profilePhoto"></div><div class="profileText"><p>Hello ' + user.fullName + '!</p></div></div>');

            // The avatar thumbnail is always defined, either by the default Speakap image or by a custom avatar.
            if (typeof(user.avatarThumbnailUrl) !== 'undefined') {
                $('div.profilePhoto').append('<img src="' + user.avatarThumbnailUrl + '" alt="user avatar">');
            }

        });
        console.log("calling ajax");
        Speakap.ajax("users", {
            success: function (speakapusers, status, jqXHR) {
                console.log("ajax called");
                console.log(speakapusers);
            },
            error: function (errorobj, status, errorthrown) {
                console.log(errorobj);
                console.log(status);
                console.log(errorthrown);
            }
        });

    });

</script>
</body>
</html>