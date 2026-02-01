<?php
use PB_LTI\Services\SecretVault;

function pb_lti_secret_page() {
    if (isset($_POST['issuer'], $_POST['client_secret'])) {
        SecretVault::store($_POST['issuer'], $_POST['client_secret']);
        echo '<div class="updated"><p>Secret stored securely.</p></div>';
    }

    echo '<h1>LTI Client Secrets</h1>
    <form method="post">
        <input name="issuer" placeholder="Platform Issuer" required>
        <input name="client_secret" placeholder="Client Secret" required type="password">
        <button>Save Secret</button>
    </form>';
}
