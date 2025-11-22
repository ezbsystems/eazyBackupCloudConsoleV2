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

    #reseller-signup {
        max-width: 1200px;
        margin: 0 auto;
        font-size: 16px;
    }

    #reseller-signup h1, h2, h3 {
        font-weight: 600;
        max-width: 600px;
        color: #002e70;
    }

    #reseller-signup label {
        font-weight: 400;
    }

    #reseller-signup .heading {
        margin: 0 auto 40px auto;
    }

    #reseller-signup .signup-content {
        padding: 20px;
        position: relative;
        display: flex;
        margin-right: 10px;
        border-radius: 5px;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.25), 0 3px 20px 0 rgba(0, 0, 0, 0.15);
    }

    #reseller-signup .benefits {
        margin: 40px;
        flex: 1;
    }

    #reseller-signup .benefits :first-child {
        margin-top: 0;
    }

    #reseller-signup .benefits :last-child {
        margin-bottom: 0;
    }

    #reseller-signup .signup-form {
        width: 340px;
        margin: 16px 0;
        padding: 30px;
        transform: translateX(40px);
        background: #fff;
        border-radius: 5px;
        box-shadow: 0 0 0 1px rgba(89,105,128,0.1), 0 3px 20px 0 rgba(89,105,128,0.3), 0 1px 2px 0 rgba(0,0,0,0.05);
    }

    #reseller-signup input:focus {
        box-shadow: 0 0 6px rgba(121, 88, 159, 0.2);
        border-color: #002e70;
        outline: none;
    }

    #reseller-signup .btn-primary {
        background-color: #002e70;
        border-color: #fff;
        color: #fff;
        font-weight: normal;
        font-size: 14px;
        padding: 16px 0;
        margin-bottom: 16px;
    }

    #reseller-signup .btn-primary:hover {
        background-color: #002252;
        transition: background 0.2s linear;
    }
</style>

<div id="reseller-signup">
    <h1 class="heading text-center">Create Your Account Today</h1>

    {if !empty($errors["error"])}
        <div class="alert alert-danger" role="alert">{$errors["error"]}</div>
    {/if}

    <div class="signup-content">
        <div class="benefits">
            <h3>Partner Benefits</h3>
            <p>Our Reseller Program offers a tiered path for partners to grow their businesses leveraging our Canadian backup platform.</p>

            <hr>

            <h3>Private Label Brand</h3>
            <p>Leverage your own pricing using the private label version.</p>

            <hr>

            <h3>Central Monitoring & Management</h3>
            <p>Monitor and manage all your accounts from one central location.</p>
        </div>
        <div class="signup-form">
            <form id="reseller" method="post" action="{$modulelink}&a=reseller">
                <div class="form-group {if !empty($errors["firstname"])}has-error{/if}">
                    <label for="firstname">First name</label>
                    <input type="text" class="form-control" id="firstname" name="firstname" {if !empty($POST["firstname"])}value="{$POST["firstname"]}"{/if}>
                    <span id="firstname-help" class="help-block">{$errors["firstname"]}</span>
                </div>

                <div class="form-group {if !empty($errors["lastname"])}has-error{/if}">
                    <label for="lastname">Last name</label>
                    <input type="text" class="form-control" id="lastname" name="lastname" {if !empty($POST["lastname"])}value="{$POST["lastname"]}"{/if}>
                    <span id="lastname-help" class="help-block">{$errors["lastname"]}</span>
                </div>

                <div class="form-group {if !empty($errors["companyname"])}has-error{/if}">
                    <label for="companyname">Company</label>
                    <input type="text" class="form-control" id="companyname" name="companyname" {if !empty($POST["companyname"])}value="{$POST["companyname"]}"{/if}>
                    <span id="companyname-help" class="help-block">{$errors["companyname"]}</span>
                </div>

                <div class="form-group {if !empty($errors["email"])}has-error{/if}">
                    <label for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" {if !empty($POST["email"])}value="{$POST["email"]}"{/if}>
                    <span id="email-help" class="help-block">{$errors["email"]}</span>
                </div>

                <div class="form-group {if !empty($errors["password"])}has-error{/if}">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <span id="password-help" class="help-block">{$errors["password"]}</span>
                </div>

                <div class="form-group {if !empty($errors["confirmpassword"])}has-error{/if}">
                    <label for="confirmpassword">Confirm password</label>
                    <input type="password" class="form-control" id="confirmpassword" name="confirmpassword">
                    <span id="confirmpassword-help" class="help-block">{$errors["confirmpassword"]}</span>
                </div>

                <div class="form-group {if !empty($errors["reseller"])}has-error{/if}">
                    <div>
                        <label for="reseller">Do you currently resell online backup?</label>
                    </div>

                    <label class="radio-inline">
                        <input type="radio" name="reseller" value="1" {if (!empty($POST["reseller"]) && $POST["reseller"] == "1")}checked{/if}>Yes
                    </label>

                    <label class="radio-inline">
                        <input type="radio" name="reseller" value="0" {if (!empty($POST["reseller"]) && $POST["reseller"] == "1")}checked{/if}>No
                    </label>

                    <span id="reseller-help" class="help-block">{$errors["reseller"]}</span>
                </div>

                <div class="form-group {if !empty($errors["numaccounts"])}has-error{/if}">
                    <div>
                        <label for="numaccounts">How many accounts do you have?</label>
                    </div>

                    <select class="form-control" id="numaccounts" name="numaccounts">
                        <option value="">Please choose</option>
                        <option>1-10</option>
                        <option>11-25</option>
                        <option>26-100</option>
                        <option>100+</option>
                    </select>

                    <span id="numaccounts-help" class="help-block">{$errors["numaccounts"]}</span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">Sign Up</button>

                <small class="terms text-muted">By signing up you agree to the <a href="https://eazybackup.ca/terms/" target="_top">Terms of Service</a> and <a href="https://eazybackup.ca/privacy/" target="_top">Privacy Policy.</a></small>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        $('#header, #main-menu, #footer, #frmGeneratePassword, #modalAjax, .header-lined').remove();
    });
</script>
