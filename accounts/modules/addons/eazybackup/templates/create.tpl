<div class="main-section-header">
    <div class="main-section-header-top">
        <h1>Service Management</h1>
    </div>
    <div class="main-section-header-tabs tabs">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link tab selected" href="https://accounts.eazybackup.ca/clientarea.php?action=products" data-tab="tab1">My Accounts</a>
            </li>
            <li class="nav-item">
                <a class="nav-link tab" href="https://accounts.eazybackup.ca/index.php?m=eazybackup&a=services" data-tab="tab2">Servers</a>
            </li>
        </ul>
    </div>
</div>

<div class="main-section-content">
    <div class="service-card table-container clearfix">      
        <div class="card-body">      

<div class="col-md-6 col-form">

            {if !empty($errors["error"])}
                <div class="alert alert-danger" role="alert">{$errors["error"]}</div>
            {/if}
               
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
                                <label for="product">Choose a plan to trial</label>
                            </div>

                        <select id="product" name="product" class="form-control">
                            {foreach $products as $i => $product}
                                <option value="{$product["pid"]}" {if (!empty($POST["product"]) && $POST["product"] == $product["pid"]) || empty($POST) && $i == 0}selected{/if}>{$product["name"]}</option>
                            {/foreach}
                        </select>

                            <span id="product-help" class="help-block">{$errors["product"]}</span>
                        </div>

                        

                        <button type="submit" class="btn btn-primary btn-lg">Create</button>

                    </ul>
                    

                        {* <p>By signing up you agree to our <br><a href="https://eazybackup.com/terms/" target="_top">Terms of Service</a> and <a href="https://eazybackup.com/privacy/" target="_top">Privacy Policy.</a></p> *}
                    </form>
        </div>
    </div>     
</div>

{* <script>
    $(document).ready(function(){
        $('#header, #main-menu, #footer, #frmGeneratePassword, #modalAjax, .header-lined').remove();
    });
</script> *}
