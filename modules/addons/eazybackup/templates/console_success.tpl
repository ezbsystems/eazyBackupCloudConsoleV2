<style>
    #header,
    #main-menu,
    #footer,     {
        /*display: none;*/
    }

    .header-lined {
        display: none;
    }

    section#main-body {
        /* background-image: URL(/../templates/eazyBackupV3/img/bg-2.png); */
        /* background-position: top;
        background-repeat: no-repeat;
        background-attachment: fixed;
        background-size: cover; */
    }

    #download {
        max-width: 800px;
        margin: 80px auto;
        font-size: 16px;
        text-align: center;
    }

    #download .heading {
        margin: 40px auto;
    }

    #download .success {
        height: 96px;
        width: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-image: radial-gradient(#fe7800 0%, #fe5000 100%);
        margin: 25px auto 25px auto;
        border-radius: 128px;
    }

    #download .success > i.fa {
        font-size: 64px;
        color: #fff;
    }

    hr {
        margin: 40px 0;
    }

    .download, .support {
        display: flex;
        flex-direction: column;
        align-items: center;
        max-width: 450px;
        margin: 0 auto;
    }

    .download h2 {
        margin-bottom: 24px;
    }
</style>

<div id="download">
    <h1>Sign-Up Successful!</h1>
    <div class="signup-content">
        <div class="success">
            <i class="fa fa-check"></i>
        </div>

        <div class="download">            
            <p>Our team is currently processing your request to provision your dedicated cloud backup server and configure your custom domain</p>
            <p>
                <strong>What to Expect Next:</strong><br>
                <ul>
                    <li>Expect an email from our support team within the next 24 hours with further instructions and details.</li>
                    <li>Once the server has been provisioned, you will be able to login, complete your branding and create your first backup users</li>
                </ul>
            </p>
            <p>Thank you for choosing eazyBackup! We look forward to supporting your business.</p>

            
        </div>
    </div>
</div>


