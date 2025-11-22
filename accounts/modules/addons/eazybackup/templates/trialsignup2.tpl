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
      padding: 0;
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
        max-width: 1200px;
        margin: 0 auto;
        font-size: 16px;
    }

    #trial-signup h1 {
        font-weight: 600;
        max-width: 600px;
        color: #fe5000;
    }

    #trial-signup h2, h3 {
        font-size: 18px !important;
        font-weight: 600 !important;
        color: #3f3f44 !important;
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
        background: #fff;
        padding: 30px 20px;
        border-radius: 20px;
        flex: 0 0 100%;
        max-width: 96%;
        box-shadow: 0px 0px 15px rgb(0 0 0 / 9%);
        margin: 0 auto;
        margin-bottom: 20px;
    }

    #trial-signup .btn-primary {
        border: none;
        color: #fff;
        font-weight: normal;
        font-size: 16px;
        padding: 18px 0;
        border-radius: 10px;
        margin: 0 auto;
        display: block;
        min-height: 44px;
    }
    form#signup {
        display: table;
        clear: both;
    }
    form#signup .form-control:hover {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
    form#signup input:focus {
        box-shadow: 0 0 6px rgba(121, 88, 159, 0.2);
        border-color: #002e70;
        outline: none;
    }

    #trial-signup .btn-primary:hover {
        background-color: #002252;
        transition: background 0.2s linear;
    }

    form#signup .form-group {
        padding: 0 15px 10px;
        width: 50%;
    }
    #trial-signup label img {
        max-width: 50px;
        margin: 0 10px 0 0;
    }
    #trial-signup label {
        font-weight: 400;
        margin-right: 10px;
        padding: 0;
        font-size: 14px;
    }
    #trial-signup .heading {
        margin: 30px auto;
        font-size: 22px;
    }
    #trial-signup .signup-btn {
        padding: 15px;
        display: block;
        width: 100%;
    }
    #trial-signup p.muted {
        text-align: center;
        width: 100%;
    }

    ul.credit-list {
        text-align: center;
        padding: 0 15px;
        width: 100%;
        margin: 6px 0 0;
    }

    #trial-signup .signup-form form#signup {
        display: flex;
        flex-wrap: wrap;
    }

    ul.credit-list .text-success {
        color: #fe501b;
        width: 30px;
        height: 30px;
        background: #ffe8df;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 4px;
    }
    ul.credit-list .list-inline-item:not(:last-child) {
        margin-right: 20px;
    }
    .plan-info-icon {
        position: relative;
    }
    .plan-info-icon:hover .plan-info-tooltip {
        display: block;
        opacity: 1;
    }
    .plan-info-tooltip .cust-toltip-sec {
        display: flex;
    }
   .plan-info-tooltip  .first-icon-sec {
        max-width: 55px;
    }
    .plan-info-tooltip .scend-text-sec {
        padding: 0 0 0 10px;
    }
    .plan-info-tooltip ul {
        padding: 0;
        margin: 10px 0 0 0;
    }
    .plan-info-tooltip ul li {
        padding: 0 0 4px 22px;
        display: block;
        position: relative;
        color: #fff;
        font-size: 12px;
    }
    .plan-info-tooltip ul li:before {
        content: ' ';
        position: absolute;
        left: 5px;
        top: 4px;
        width: 6px;
        height: 12px;
        border-right: 2px solid #fff;
        border-bottom: 2px solid #fff;
        transform: rotate(45deg);
    }
    .plan-info-tooltip ul li span {
        color: #fff;
    }
    .plan-info-tooltip br {
        display: none;
    }
    .plan-info-tooltip  .first-icon-sec {
        max-width: 50px;
    }
   .plan-info-tooltip .first-icon-sec img {
        width: 100%;
    }
    .plan-info-tooltip .scend-text-sec h4 {
        font-size: 13px;
        margin: 0 0 2px 0;
    }
    #trial-signup .signup-form .form-group.has-error .radio-inline {
        color: inherit;
    }
    form#signup  .form-group.custom-redio {
        width: 100%;
    }
    form#signup  .form-group.custom-redio {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
    }
    form#signup .form-group.custom-redio label.radio-inline {
        width: 25%;
        margin: 0 !important;
        padding: 10px;
    }
    form#signup .form-group.custom-redio label.radio-inline input[type="radio"] {
        right: 20px;
        top: 15px;
    }
    .plan-info-tooltip.active {
        background: #FE5000;
    }
    .plan-info-tooltip {
        background: #143d5f;
        padding: 20px;
        border-radius: 15px;
        color: #fff;
    }
    .cust-free-tag {
        margin: 0 0 5px;
    }
    .cust-free-tag span {
        color: #fff;
    }
    .scend-text-sec h4 {
        color: #fff;
    }
    .scend-text-sec p {
        color: #fff !important;
        font-weight: 600;
        margin-top: 0px;
    }
    .choose-plan-title {
        padding: 0 0 0 15px;
    }
    label.error-messages {
        width: 100%;
        font-size: 80%;
        color: #dc3545;
        line-height: 1.3;
        margin-bottom: 0;
        margin-top: 5px;
    }
    .intl-tel-input .selected-flag {
        height: auto;
        min-height: 42px;
    }
    .form-control {
        margin-bottom: 0;
    }


    @media only screen and (max-width:991px) {
        #trial-signup .signup-btn {
            padding: 15px 0;
        }
        ul.credit-list {
            padding: 0;
        }
        ul.credit-list .text-success {
            width: 22px;
            height: 22px;
            font-size: 11px;
        }
    }
    @media only screen and (max-width:767px) {
        form#signup .form-group {
            padding: 0;
            width: 100%;
        }
        ul.credit-list {
            text-align: left;
        }
        ul.credit-list .list-inline-item {
            display: block;
            padding: ;
        }
        ul.credit-list .list-inline-item:not(:last-child) {
            margin-right: 0;
            margin-bottom: 10px;
        }
    }

</style>

<div id="trial-signup">
    {if !empty($errors["error"])}
        <div class="alert alert-danger" role="alert">{$errors["error"]}</div>
    {/if}
    {if !empty($POST['useradded'])}
        <p>UserId: {$POST['useradded']}</p>
    {/if}
    {if !empty($POST['orderadded'])}
        <p>OrderId: {$POST['orderadded']}</p>
    {/if}
    <div class="signup-form">
        <form id="signup" method="post" action="{$modulelink}&a=signup">
            <div class="form-group {if !empty($errors["email"])} has-error {/if}">
                <label for="email">Email address</label>
                <input type="email" class="form-control" id="email" name="email" {if !empty($POST['email'])} value="{$POST['email']}" {/if}>
                <span id="email-help" class="help-block">{$errors["email"]}</span>
            </div>

            <div class="form-group {if !empty($errors["username"])} has-error {/if}">
                <label for="email">Username</label>
                <input type="text" class="form-control" id="username" name="username" {if !empty($POST['username'])} value="{$POST['username']}" {/if}>
                <span id="username-help" class="help-block">{$errors["username"]}</span>
            </div>

            <div class="form-group {if !empty($errors["password"])} has-error {/if}">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" {if !empty($POST['password'])} value="{$POST['password']}" {/if}>
                <span id="password-help" class="help-block">{$errors["password"]}</span>
            </div>

            <div class="form-group {if !empty($errors["confirmpassword"])} has-error {/if}">
                <label for="confirmpassword">Confirm password</label>
                <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" {if !empty($POST['confirmpassword'])} value="{$POST['confirmpassword']}" {/if}>
                <span id="confirmpassword-help" class="help-block">{$errors["confirmpassword"]}</span>
            </div>

            <div class="form-group {if !empty($errors["phonenumber"])} has-error {/if}">
                <label for="phonenumber">Phone number</label>
                <input type="text" class="form-control" id="phonenumber" name="phonenumber" {if !empty($POST['phonenumber'])} value="{$POST['phonenumber']}"{/if}>
                <span id="phonenumber-help" class="help-block">{$errors["phonenumber"]}</span>
            </div>

            <div class="choose-plan">
                <div class="choose-plan-title">
                    <label for="product">Choose a plan</label>
                </div>
                <div class="form-group custom-redio">
                    {foreach $products as $i => $product}
                        <label class="radio-inline">
                            <input type="radio" name="plan" value="{$product['pid']}" {if (!empty($POST['plan']) && $product['pid'] == $POST['plan'])} checked {elseif empty($POST['plan']) && 0 == $i} checked {/if}>
                            <div class="plan-info-tooltip {if (!empty($POST['plan']) && $product['pid'] == $POST['plan'])} active {elseif empty($POST['plan']) && 0 == $i} active {/if}">
                                <div class="cust-free-tag">
                                    <span>Free</span>
                                </div>
                                <div class="cust-toltip-sec">
                                    <div class="first-icon-sec">
                                        <img src="../modules/addons/eazybackup/templates/assets/images/cloud-online-backup.svg" alt="toltip-img">
                                    </div>
                                    <div class="scend-text-sec">
                                        <h4>{$product["name"]}</h4>
                                        <p>$ 0.00</p>
                                    </div>
                                </div>
                                {$product['description']|nl2br}
                            </div>
                        </label>
                    {/foreach}
                </div>
            </div>

            <div class="signup-btn">
                <button type="submit" class="btn btn-primary btn-lg">Sign Up</button>
            </div>
            <ul class="credit-list">
                <li class="list-inline-item"><i class="fa fa-check text-success"></i> No credit card required</li>
                <li class="list-inline-item"><i class="fa fa-check text-success"></i> 14-day free trial</li>
            </ul>
            <p class="muted">By signing up you agree to our <a href="https://eazybackup.ca/terms/" target="_top">Terms of Service</a> and <a href="https://eazybackup.ca/privacy/" target="_top">Privacy Policy.</a></p>
        </form>
    </div>
</div>
<script>
    $(document).ready(function(){
        $('#header, #main-menu, #footer, #frmGeneratePassword, #modalAjax, .header-lined').remove();
    });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js" integrity="sha512-rstIgDs0xPgmG6RX1Aba4KV5cWJbAMcvRCVmglpam9SoHZiUCyQVDdH2LPlxoHtrv17XWblE/V/PP+Tr04hbtA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/additional-methods.min.js" integrity="sha512-6S5LYNn3ZJCIm0f9L6BCerqFlQ4f5MwNKq+EthDXabtaJvg3TuFLhpno9pcm+5Ynm6jdA9xfpQoMz2fcjVMk9g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
{literal}
<script>
    jQuery(document).ready(function() {
        jQuery.validator.addMethod("regex", function(value, element, regexp) {
            if (regexp.constructor != RegExp) {
                regexp = new RegExp(regexp);
            } else if (regexp.global) {
                regexp.lastIndex = 0;
            }

            return this.optional(element) || regexp.test(value);
        }, "Invalid value");

        const usernameRegex = /^[a-zA-Z0-9_\.-]{6,}$/;
        const emailRegex = /^[a-zA-Z]+(?!.*[\_\-\.]{2}).*[a-zA-Z0-9_\.\-]{2,}[a-zA-Z0-9]{1}@[a-zA-Z]+(\.[a-zA-Z]+)?[\.]{1}[a-zA-Z]{2,10}$/;
        const phoneNumberRegex = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;

        jQuery('input[name="phonenumber"]').keyup(function(e) {
            if (e.keyCode == 8 || e.keyCode == 46) {
                return false;
            }

            var regex =/^[0-9]*$/;
            var number = jQuery(this).val().split("-").join("");
            if (!regex.test(number)) {
                number = number.split(e.key).join("");
            }
            if (number.length > 10) {
                number.slice(0,-1);
            }
            var finalNumber = "";
            if (0 < number.length) {
                finalNumber = finalNumber + number.substring(0,3);
            }
            if (4 <= number.length) {
                finalNumber = finalNumber + "-" + number.substring(3, (number.length > 6 ? 6 : number.length));
            }
            if (6 < number.length) {
                finalNumber = finalNumber + "-" + number.substring(6, (number.length > 10 ? 10 : number.length));
            }

            jQuery(this).val(finalNumber);
        });

        jQuery('input[name="plan"]').click(function() {
            jQuery('.plan-info-tooltip').removeClass('active');
            jQuery(this).siblings('.plan-info-tooltip').addClass('active');
        });

        jQuery('form#signup').validate({
            errorClass: "error-messages",
            rules: {
                email: {
                    required: true,
                    email: true,
                    regex: emailRegex
                },
                username: {
                    required: true,
                    minlength: 6,
                    regex: usernameRegex
                },
                phonenumber: {
                    required: true,
                    regex: phoneNumberRegex
                },
                password: {
                    required: true,
                    minlength: 8,
                    maxlength: 32,
                },
                confirmpassword: {
                    required: true,
                    minlength: 8,
                    maxlength: 32,
                    equalTo: "#password"
                },
                plan: {
                    required: true
                },
            },
            messages: {
                email: {
                    required: 'Please enter the email',
                    email: 'Invalid email address',
                    regex: 'Invalid email address',
                },
                username: {
                    required: 'Please enter the comet username',
                    minlength: 'Username must be at least 6 characters long',
                    regex: 'Username can contain only a-z, A-Z, 0-9, _, ., -'
                },
                phonenumber: {
                    required: 'Please enter the phone number',
                    regex: 'Invalid phone number'
                },
                password: {
                    required: 'Please enter the password',
                    minlength: 'Password must be at least 8-32 characters long',
                    maxlength: 'Password must be at least 8-32 characters long',
                },
                confirmpassword: {
                    required: 'Please enter the confirm password',
                    minlength: 'Confirm password must be at least 8-32 characters long',
                    maxlength: 'Confirm password must be at least 8-32 characters long',
                    equalTo: 'Confirm password must be same as password '
                },
                plan: {
                    required: 'Please select the plan'
                },
            }
        })
    });
</script>
{/literal}