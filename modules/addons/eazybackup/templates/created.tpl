<style>
    #header,
    #main-menu,
    #footer,
    .header-lined {
        display: none;
    }

    #download {
        max-width: 800px;
        margin: 80px auto;
        font-size: 16px;
        text-align: center;
    }

    #download h1, h2, h3 {
        font-weight: 600;
        max-width: 600px;
        color: #fe5000;
    }

    #download .heading {
        margin: 40px auto;
    }
    
    #download .success {
        height: 128px;
        width: 128px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #002e70;
        margin: 0 auto;
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
    <h1 class="medium-heading text-center">Thanks for creating an account!</h1>
    <div class="signup-content">
        <div class="success">
            <i class="fa fa-check"></i>
        </div>

        <div class="download">
            <h2><small>Click below to continue to the Client Area.</small></h2>
            <a type="button" class="btn btn-lg btn-primary" href="clientarea.php" target="_top">Continue to Client Area</a>
        </div>

    </div>
</div>
