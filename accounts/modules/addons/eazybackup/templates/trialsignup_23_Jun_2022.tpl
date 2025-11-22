<style>
    body {
        background: #fff !important;
    }

    #header,
    #main-menu,
    #footer,
    .header-lined {
        display: none;
    }

    section#main-body {
      padding: 50px;
    }

    p {
        font-size: 14px !important;
        color: #3f3f44 !important;
    }

    #muted p {
        font-size: 12px !important;
        color: #3f3f44 !important;
    }



    #trial-signup {
        max-width: 800px;
        margin: 0 auto;
        font-size: 16px;
    }

    #trial-signup h1 {
        font-size: 32px !important;
        font-weight: 600;
        max-width: 600px;
        color: #fe5000;
    }

    #trial-signup h2, h3 {
        font-size: 18px !important;
        font-weight: 600 !important;
        color: #3f3f44 !important;
    }

    #trial-signup label {
        font-weight: 400;
        margin-right: 10px;
    }

    #trial-signup .heading {
        margin: 0 auto 40px auto;
    }

    #trial-signup .signup-content {
        padding: 20px;
        position: relative;
        display: flex;
        margin-right: 10px;
        border-radius: 5px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.25), 0 3px 5px 0 rgba(0, 0, 0, 0.15);
    }

    #trial-signup .benefits {
        margin: 40px;
        flex: 1;
    }

    #trial-signup .benefits :first-child {
        margin-top: 0;
    }

    #trial-signup .benefits :last-child {
        margin-bottom: 0;
    }

    #trial-signup .signup-form {
        width: 700px;
        margin: 16px 0;
        padding: 30px;
        transform: translateX(40px);
        background: #fff;
        border-radius: 5px;
        box-shadow: 0 0 0 1px rgba(89,105,128,0.1), 0 3px 5px 0 rgba(89,105,128,0.3), 0 1px 2px 0 rgba(0,0,0,0.05);
    }

    #trial-signup input:focus {
        box-shadow: 0 0 6px rgba(121, 88, 159, 0.2);
        border-color: #fe5000;
        outline: none;
    }

    #trial-signup .btn-primary {
        background-color: #fe5000;
        border-color: #fe5000;
        color: #ffffff;
        font-weight: 600;
        font-size: 14px;
        letter-spacing: 1px;
        padding: 16px 0;
        margin-bottom: 16px;
    }

    #trial-signup .btn-primary:hover {
        background-color: #e05900;
        transition: background 0.2s linear;
    }
</style>

<div id="trial-signup">
    <h1 class="heading text-center">Try eazyBackup Free For 14 Days</h1>

    {if !empty($errors["error"])}
        <div class="alert alert-danger" role="alert">{$errors["error"]}</div>
    {/if}
        <div class="signup-form">
            <form id="signup" method="post" action="{$modulelink}&a=signup">
                <div class="form-group {if !empty($errors["username"])}has-error{/if}">
                    <label for="email">Choose a username for your account</label>
                    <input type="text" class="form-control" id="username" name="username" {if !empty($POST["username"])}value="{$POST["username"]}"{/if}>
                    <span id="username-help" class="help-block">{$errors["username"]}</span>
                </div>

                <div class="form-group {if !empty($errors["username"])}has-error{/if}">
                    <label for="phonenumber">Phone number</label>
                    <input type="text" class="form-control" id="phonenumber" name="phonenumber" {if !empty($POST["phonenumber"])}value="{$POST["phonenumber"]}"{/if}>
                    <span id="phonenumber-help" class="help-block">{$errors["phonenumber"]}</span>
                </div>

                <div class="form-group {if !empty($errors["password"])}has-error{/if}">
                    <label for="password">Create a strong password</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <span id="password-help" class="help-block">{$errors["password"]}</span>
                </div>

                <div class="form-group {if !empty($errors["confirmpassword"])}has-error{/if}">
                    <label for="confirmpassword">Confirm password</label>
                    <input type="password" class="form-control" id="confirmpassword" name="confirmpassword">
                    <span id="confirmpassword-help" class="help-block">{$errors["confirmpassword"]}</span>
                </div>

                <div class="form-group {if !empty($errors["email"])}has-error{/if}">
                    <label for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" {if !empty($POST["email"])}value="{$POST["email"]}"{/if}>
                    <span id="email-help" class="help-block">{$errors["email"]}</span>
                </div>

                <div class="form-group {if !empty($errors["product"])}has-error{/if}">
                    <div>
                        <label for="product">Choose a plan</label>
                    </div>

                    {foreach $products as $i => $product}
                        <label class="radio-inline">
                            <input type="radio" name="product" value="{$product["pid"]}" {if (!empty($POST["product"]) && $POST["product"] == $product["pid"]) || empty($POST) && $i == 0}checked{/if}>{$product["name"]}
                        </label>
                    {/foreach}

                    <span id="product-help" class="help-block">{$errors["product"]}</span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Sign Up</button>

                <li class="list-inline-item"><i class="fa fa-check text-success"></i> No credit card required</li>
                <li class="list-inline-item"><i class="fa fa-check text-success"></i> 14-day free trial</li>

                <p class="muted">By signing up you agree to our <br><a href="https://eazybackup.ca/terms/" target="_top">Terms of Service</a> and <a href="https://eazybackup.ca/privacy/" target="_top">Privacy Policy.</a></p>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        $('#header, #main-menu, #footer, #frmGeneratePassword, #modalAjax, .header-lined').remove();
    });
</script>
