<style>
    .responsive-video {
        width: 100%;
        height: auto;
    }
</style>

<div class="main-section-header">
    <div class="main-section-header-top">
        <h1>Your Microsoft 365 backup account has been successfully created</h1>
    </div>
</div>

<div class="main-section-content">
    <div class="main-section-content section">
        <div class="service-card table-container clearfix">

            <div style="border-bottom: 1px solid rgba(229,231,235); padding: 15px">
                <p>Next Steps:</p>
                <ul>
                    <p><strong>1. Log In to the Control Panel</strong></p>
                    <p>Control Panel:</strong> <a href="https://panel.obcbackup.com/">https://panel.obcbackup.com/</a>
                    </p>
                    <p>Username:</strong> {$username}</p>
                    <p>Password:</strong> (Use the password you selected during sign-up)</p>
                    <p><strong>2. Configure Your Backup</strong></p>
                    <p>Once logged in, select Protected Items -> 'Add new Protected Item' to set up and customize your
                        MS 365 backup.
                    <p>Watch the step-by-step configuration video below. As always, if you need further assistance, our
                        support team is ready to help.</p>
            </div>
            <div class="container p-3">

                <div class="row">
                    <!-- Text Div -->

                    <div class="col-12 col-text mb-3">


                    </div>
                    <!-- Video Div -->
                    <div class="col-12 col-text">
                        <video class="responsive-video" controls>
                            <source
                                src="https://eazybackup.com/wp-content/uploads/2024/05/MS365_Backup_Getting_Started.mp4"
                                type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var loginForm = document.getElementById("whmcsLoginForm");
        loginForm.addEventListener("submit", function() {
            var redirectTo = '/clientarea.php?action=details';
            var hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "goto";
            hiddenInput.value = redirectTo; // No encoding needed for direct path
            loginForm.appendChild(hiddenInput);
        });
    });
</script>