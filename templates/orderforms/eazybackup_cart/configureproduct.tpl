{include file="orderforms/eazybackup_cart/common.tpl"}


<style>
/* Hide number input spinners for Chrome, Safari, Edge, and Opera */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Hide number input spinners for Firefox */
input[type=number] {
    -moz-appearance: textfield;
}

.spinner {
    animation: spin 1s linear infinite;
    transform-origin: center;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}


</style>

<script>
var _localLang = {
    'addToCart': '{$LANG.orderForm.addToCart|escape}',
    'addedToCartRemove': '{$LANG.orderForm.addedToCartRemove|escape}'
}
</script>



<!-- Refactored Template: eazybackup_cart/configureproduct.tpl -->
<div id="order-standard_cart" class="bg-gray-700 min-h-screen py-8">
    <div class="max-w-5xl m-4 shadow">

        <!-- Top-Level Layout Grid -->
        <div>

            <!-- Main Cart Body -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Configuration Form -->
                <form 
                    id="frmConfigureProduct" 
                    method="post" 
                    class="space-y-6 bg-white shadow p-4 rounded-t-lg"
                >
                    <input type="hidden" name="configure" value="true" />
                    <input type="hidden" name="i" value="{$i}" />

                    <!-- Intro & Product Info -->
                    <div class="border-b border-gray-200 pb-4">
                        <h2 class="text-xl font-semibold text-gray-700 mb-2">
                            {$productinfo.name}
                        </h2>                      


                    </div>

                    <!-- Billing Cycle -->
                    {if $pricing.type eq "recurring"}
                    <div>
                        <label 
                            for="inputBillingcycle" 
                            class="block text-sm font-medium text-gray-700 mb-1"
                        >
                            {$LANG.cartchoosecycle}
                        </label>
                        <select
                            name="billingcycle" 
                            id="inputBillingcycle"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/"
                            onchange="updateConfigurableOptions({$i}, this.value); return false"
                        >
                            {if $pricing.monthly}
                            <option value="monthly"{if $billingcycle eq "monthly"} selected{/if}>
                                Monthly
                            </option>
                            {/if}
                            {if $pricing.quarterly}
                            <option value="quarterly"{if $billingcycle eq "quarterly"} selected{/if}>
                                {$pricing.quarterly}
                            </option>
                            {/if}
                            {if $pricing.semiannually}
                            <option value="semiannually"{if $billingcycle eq "semiannually"} selected{/if}>
                                {$pricing.semiannually}
                            </option>
                            {/if}
                            {if $pricing.annually}
                            <option value="annually"{if $billingcycle eq "annually"} selected{/if}>
                                Annual
                            </option>
                            {/if}
                            {if $pricing.biennially}
                            <option value="biennially"{if $billingcycle eq "biennially"} selected{/if}>
                                {$pricing.biennially}
                            </option>
                            {/if}
                            {if $pricing.triennially}
                            <option value="triennially"{if $billingcycle eq "triennially"} selected{/if}>
                                {$pricing.triennially}
                            </option>
                            {/if}
                        </select>
                    </div>                            
                    {/if} 
                    
                    <!-- Custom Fields -->
                    {if $customfields}
                        <div class="space-y-4">
                            <div class="bg-sky-50 p-3 rounded-md">
                                <h3 class="font-semibold text-sky-700">
                                    {$LANG.orderadditionalrequiredinfo}
                                </h3>
                            </div>

                        <!-- Validation Errors -->
                        <div 
                            class="hidden bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 mt-4 transition-opacity duration-300 flex items-start"
                            role="alert"
                            id="containerProductValidationErrors"
                        >
                            <p>{$LANG.orderForm.correctErrors}:</p>
                            <ul 
                                id="containerProductValidationErrorsList"
                                class="mt-2 list-disc list-inside text-sm"
                            ></ul>
                        </div>
                            
                        <div class="space-y-4">
                                {foreach $customfields as $customfield}
                                    <div>
                                        <label 
                                            for="customfield{$customfield.id}"
                                            class="block text-sm font-medium text-gray-700"
                                        >
                                            {$customfield.name} {$customfield.required}
                                        </label>
                                        <div class="mt-1">
                                        {$customfield.input|replace:'form-control':'lock w-full rounded-md bg-white px-3 py-2 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-sky-600 sm:text-sm/6'}
                                        </div>
                                        {if $customfield.description}
                                <p class="text-xs text-gray-500 mt-1">
                                                {$customfield.description}
                                </p>
                                        {/if}
                                    </div>
                                {/foreach}
                            </div>
                    </div>
                    {/if}                    
                    
                    <!-- Configurable Options -->
                    {if $configurableoptions}
                        <div class="space-y-4">
                            <!-- Order Config Package Header -->
                            <div class="bg-sky-50 p-3 rounded-md">
                                <h3 class="font-semibold text-sky-700">
                                    {$LANG.orderconfigpackage}
                                </h3>
                            </div>

                            {* =================================== *}
                            {* ========== Endpoints Category === *}
                            {* =================================== *}
                            {assign var="endpointsOptions" value=[]}
                            {foreach $configurableoptions as $configoption}
                                {if in_array($configoption.id, [88, 89])}
                                    {* Append to endpointsOptions array *}
                                    {$endpointsOptions[] = $configoption}
                                {/if}
                            {/foreach}

                            {if $endpointsOptions|@count > 0}
                                <!-- Endpoints Category Separator and Heading -->
                                <hr class="my-4 border-gray-300">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    Endpoints
                                </h4>

                                <!-- Endpoints Options Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-2 gap-6">
                                    {foreach $endpointsOptions as $configoption}
                                        {include file='orderforms/eazybackup_cart/partials/option_block.tpl' configoption=$configoption}
                                    {/foreach}
                                </div>
                            {/if}

                            <!-- Dynamic Info Message -->
                            <div 
                                id="deviceEndpointWarning" 
                                class="hidden bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 mt-4 transition-opacity duration-300 flex items-start" 
                                role="alert"
                            >
                            <!-- Info Icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                </svg>
                                <div>        
                                    <p>Please select at least one device endpoint (Workstation, Server or Synology) to continue.</p>
                                </div>
                            </div>

                            {* =================================== *}
                            {* ========== Synology Category === *}
                            {* =================================== *}
                            {assign var="endpointsOptions" value=[]}
                            {foreach $configurableoptions as $configoption}
                                {if in_array($configoption.id, [98])}
                                    {* Append to endpointsOptions array *}
                                    {$endpointsOptions[] = $configoption}
                                {/if}
                            {/foreach}

                            {if $endpointsOptions|@count > 0}
                                <!-- Endpoints Category Separator and Heading -->
                                <hr class="my-4 border-gray-300">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    Synology
                                </h4>

                                <!-- Endpoints Options Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-2 gap-6">
                                    {foreach $endpointsOptions as $configoption}
                                        {include file='orderforms/eazybackup_cart/partials/option_block.tpl' configoption=$configoption}
                                    {/foreach}
                                </div>
                            {/if}

                            {* =================================== *}
                            {* =========== Addons Category ======*}
                            {* =================================== *}
                            {assign var="addonsOptions" value=[]}
                            {foreach $configurableoptions as $configoption}
                                {if in_array($configoption.id, [97, 91])}
                                    {* Append to addonsOptions array *}
                                    {$addonsOptions[] = $configoption}
                                {/if}
                            {/foreach}

                            {if $addonsOptions|@count > 0}
                                <!-- Addons Category Separator and Heading -->
                                <hr class="my-4 border-gray-300">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    Addons
                                </h4>

                                <!-- Addons Options Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    {foreach $addonsOptions as $configoption}
                                        {include file='orderforms/eazybackup_cart/partials/option_block.tpl' configoption=$configoption}
                                    {/foreach}
                                </div>
                            {/if}

                            {* =================================== *}
                            {* ========== Storage Category ===== *}
                            {* =================================== *}
                            {assign var="storageOptions" value=[]}
                            {foreach $configurableoptions as $configoption}
                                {if $configoption.id == 67}
                                    {* Append to storageOptions array *}
                                    {$storageOptions[] = $configoption}
                                {/if}
                            {/foreach}

                            {if $storageOptions|@count > 0}
                                <!-- Storage Category Separator and Heading -->
                                <hr class="my-4 border-gray-300">
                                <h4 class="font-semibold text-gray-800 mb-2">
                                    Additional Storage
                                </h4>

                                <!-- Storage Options Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    {foreach $storageOptions as $configoption}
                                        {include file='orderforms/eazybackup_cart/partials/option_block.tpl' configoption=$configoption}
                                    {/foreach}
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Submit Button -->
                    <div class="pt-4 text-center">
                        <button
                            type="submit"
                            id="btnCompleteProductConfig"
                            class="inline-flex items-center bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold py-3 px-6 rounded-md transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {$LANG.continue}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 ml-2 fa-arrow-circle-right" id="submitButtonIcon">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                
                
                    </div>
                </form>       

            </div><!-- End Main Cart Body -->

            <!-- Order Summary -->
            <div class="lg:col-span-4 bg-white shadow p-4 rounded-b-lg">
            {include file='orderforms/eazybackup_cart/partials/order_summary.tpl'}
            </div>

        </div><!-- End Grid -->
    </div><!-- End Container -->
</div>

<!-- Trigger totals recalculation on load -->
<script>
    recalctotals();
</script>

{literal}
    <script>
        /**
         * Handles locking and unlocking logic for configurable options.
         * Disables config options #91 and #97 based on dependencies.
         */
        function handleConfigOptionDependencies() {
            // Get elements for #88, #89, #91, and #97
            const option88El = document.getElementById("inputConfigOption88");
            const option89El = document.getElementById("inputConfigOption89");
            const option91El = document.getElementById("inputConfigOption91");
            const option97El = document.getElementById("inputConfigOption97");
            const title91El = document.getElementById("titleConfigOption91");
            const title97El = document.getElementById("titleConfigOption97");
    
            if (!option88El || !option89El || !title91El || !title97El) {
                console.warn("One or more required elements are missing for dependency handling.");
                return;
            }
    
            const qty88 = Number(option88El.value) || 0;
            const qty89 = Number(option89El.value) || 0;
    
            // Option #91 logic
            if (qty88 < 1 && qty89 < 1) {
                if (option91El) {
                    option91El.disabled = true;
                }
                disableSiblingButtons(option91El);
                title91El.classList.add("text-gray-400");
                title91El.classList.remove("text-gray-800", "group-hover:text-sky-500");
            } else {
                if (option91El) {
                    option91El.disabled = false;
                }
                enableSiblingButtons(option91El);
                title91El.classList.remove("text-gray-400");
                title91El.classList.add("text-gray-800", "group-hover:text-sky-500");
            }
    
            // Option #97 logic
            if (qty89 < 1) {
                if (option97El) {
                    option97El.disabled = true;
                }
                disableSiblingButtons(option97El);
                title97El.classList.add("text-gray-400");
                title97El.classList.remove("text-gray-800", "group-hover:text-sky-500");
            } else {
                if (option97El) {
                    option97El.disabled = false;
                }
                enableSiblingButtons(option97El);
                title97El.classList.remove("text-gray-400");
                title97El.classList.add("text-gray-800", "group-hover:text-sky-500");
            }
        }
    
        function disableSiblingButtons(inputEl) {
            if (!inputEl) return;
            const parentDiv = inputEl.parentElement;
            if (parentDiv) {
                const buttons = parentDiv.querySelectorAll("button");
                buttons.forEach(btn => btn.disabled = true);
            }
        }
    
        function enableSiblingButtons(inputEl) {
            if (!inputEl) return;
            const parentDiv = inputEl.parentElement;
            if (parentDiv) {
                const buttons = parentDiv.querySelectorAll("button");
                buttons.forEach(btn => btn.disabled = false);
            }
        }
    
        document.addEventListener("DOMContentLoaded", function () {
            handleConfigOptionDependencies();
    
            const option88El = document.getElementById("inputConfigOption88");
            const option89El = document.getElementById("inputConfigOption89");
    
            if (option88El) {
                option88El.addEventListener("change", handleConfigOptionDependencies);
                option88El.addEventListener("keyup", handleConfigOptionDependencies);
            }
    
            if (option89El) {
                option89El.addEventListener("change", handleConfigOptionDependencies);
                option89El.addEventListener("keyup", handleConfigOptionDependencies);
            }
        });
    </script>
    {/literal}
{literal}
<script>
    /**
     * Decreases the value of the specified input field by 1, respecting the minimum value.
     * @param {string} inputId - The ID of the input field.
     */
    function decreaseValue(inputId) {
        var input = document.getElementById(inputId);
        var min = parseInt(input.min) || 0;
        var currentValue = parseInt(input.value) || 0;

        if (currentValue > min) {
            input.value = currentValue - 1;
            recalctotals();
            // Trigger onchange event if necessary
            input.dispatchEvent(new Event('change'));
        }
    }

    /**
     * Increases the value of the specified input field by 1, respecting the maximum value if set.
     * @param {string} inputId - The ID of the input field.
     */
    function increaseValue(inputId) {
        var input = document.getElementById(inputId);
        var max = parseInt(input.max) || Infinity;
        var currentValue = parseInt(input.value) || 0;

        if (currentValue < max) {
            input.value = currentValue + 1;
            recalctotals();
            // Trigger onchange event if necessary
            input.dispatchEvent(new Event('change'));
        }
    }

    // Optional: Prevent manual input below min
    document.addEventListener('DOMContentLoaded', function() {
        var numberInputs = document.querySelectorAll('input[type="number"][name^="configoption["]');

        numberInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                var min = parseInt(this.min) || 0;
                if (parseInt(this.value) < min) {
                    this.value = min;
                    recalctotals();
                }
            });
        });
    });
</script>
{/literal}

<script>
    /**
     * Checks whether the Submit button should appear disabled based on the values of Config options 88, 89, and 98.
     * - Adds disabled styling if all three options are 0.
     * - Removes disabled styling if any of the options is 1 or more.
     * - Hides the info message if any option is selected.
     */
    function checkSubmitButtonState() {
        const option88El = document.getElementById("inputConfigOption88");
        const option89El = document.getElementById("inputConfigOption89");
        const option98El = document.getElementById("inputConfigOption98");
        const submitButton = document.getElementById("btnCompleteProductConfig");
        const infoContainer = document.getElementById("deviceEndpointWarning");

        if (!option88El || !option89El || !option98El || !submitButton || !infoContainer) {
            console.warn("One or more elements for submit button state checking are missing.");
            return;
        }

        const qty88 = Number(option88El.value) || 0;
        const qty89 = Number(option89El.value) || 0;
        const qty98 = Number(option98El.value) || 0;

        if (qty88 === 0 && qty89 === 0 && qty98 === 0) {
            submitButton.classList.add("opacity-50", "cursor-not-allowed");
            submitButton.setAttribute("data-disabled", "true");
            submitButton.setAttribute("title", "Please select at least one device endpoint to continue.");
        } else {
            submitButton.classList.remove("opacity-50", "cursor-not-allowed");
            submitButton.removeAttribute("data-disabled");
            submitButton.setAttribute("title", "Click to continue.");

            // Hide the info message since a device endpoint is selected
            infoContainer.classList.add("hidden");
            infoContainer.classList.remove("opacity-100"); // If using opacity transitions
        }
    }


    /**
     * Initializes event listeners for Config options 88, 89, and 98
     * to dynamically check and update the Submit button state.
     */
    function initializeSubmitButtonLogic() {
        const option88El = document.getElementById("inputConfigOption88");
        const option89El = document.getElementById("inputConfigOption89");
        const option98El = document.getElementById("inputConfigOption98");

        if (option88El) {
            option88El.addEventListener("change", checkSubmitButtonState);
            option88El.addEventListener("keyup", checkSubmitButtonState);
        }

        if (option89El) {
            option89El.addEventListener("change", checkSubmitButtonState);
            option89El.addEventListener("keyup", checkSubmitButtonState);
        }

        if (option98El) {
            option98El.addEventListener("change", checkSubmitButtonState);
            option98El.addEventListener("keyup", checkSubmitButtonState);
        }

        // Initial check on page load
        checkSubmitButtonState();
    }

    /**
     * Handles the form submission event to ensure that
     * at least one of Config options 88, 89, or 98 is selected.
     * If not, it prevents the form submission and displays an info message.
     */
    function handleFormSubmission() {
        const form = document.getElementById("frmConfigureProduct");
        const errorContainer = document.getElementById("containerProductValidationErrors");
        const errorList = document.getElementById("containerProductValidationErrorsList");
        const infoContainer = document.getElementById("deviceEndpointWarning");
        const submitButton = document.getElementById("btnCompleteProductConfig");

        if (!form || !errorContainer || !errorList || !infoContainer || !submitButton) {
            console.warn("Form or related elements are missing.");
            return;
        }

        // Handle form submission
        form.addEventListener("submit", function(event) {
            const option88 = Number(document.getElementById("inputConfigOption88").value) || 0;
            const option89 = Number(document.getElementById("inputConfigOption89").value) || 0;
            const option98 = Number(document.getElementById("inputConfigOption98").value) || 0;

            if (option88 === 0 && option89 === 0 && option98 === 0) {
                event.preventDefault(); // Prevent form submission

                // Populate and display the error message
                errorList.innerHTML = '<li>You need to select at least one device endpoint in your backup plan.</li>';
                errorContainer.classList.remove("hidden");

                // Hide the info message if previously shown
                infoContainer.classList.add("hidden");
                infoContainer.classList.remove("opacity-100"); // If using opacity transitions

                // Optionally, scroll to the error message for better UX
                errorContainer.scrollIntoView({ behavior: "smooth" });
            } else {
                // Hide the error message if validation passes
                errorContainer.classList.add("hidden");
                errorList.innerHTML = '';

                // Ensure the info message is hidden
                infoContainer.classList.add("hidden");
                infoContainer.classList.remove("opacity-100"); // If using opacity transitions
            }
        });

        // Handle Submit button click
        submitButton.addEventListener("click", function(event) {
            const isDisabled = submitButton.getAttribute("data-disabled") === "true";
            const option88 = Number(document.getElementById("inputConfigOption88").value) || 0;
            const option89 = Number(document.getElementById("inputConfigOption89").value) || 0;
            const option98 = Number(document.getElementById("inputConfigOption98").value) || 0;

            if (isDisabled) {
                event.preventDefault(); // Prevent form submission

                // Show the info message
                infoContainer.classList.remove("hidden");
                infoContainer.classList.add("opacity-100"); // For fade-in effect

                // Make the info container focusable and set focus
                infoContainer.setAttribute("tabindex", "-1");
                infoContainer.focus();

                // Optionally, remove tabindex after focusing
                setTimeout(() => {
                    infoContainer.removeAttribute("tabindex");
                }, 1000);

                // Scroll to the info message for better UX
                infoContainer.scrollIntoView({ behavior: "smooth" });
            } else {
                // Ensure the info message is hidden if the button is enabled
                infoContainer.classList.add("hidden");
                infoContainer.classList.remove("opacity-100"); // If using opacity transitions
            }
        });

    }


    /**
     * Initialize all custom logic when the DOM is fully loaded
     */
    document.addEventListener("DOMContentLoaded", function () {
        handleConfigOptionDependencies(); // Existing function
        initializeSubmitButtonLogic();    // New function for submit button
        handleFormSubmission();           // New function for form submission
    });
</script>


<script>
    /**
     * Updates the tooltip of the Submit button based on the selection state.
     */
    function updateSubmitButtonTooltip() {
        const option88El = document.getElementById("inputConfigOption88");
        const option89El = document.getElementById("inputConfigOption89");
        const option98El = document.getElementById("inputConfigOption98");
        const submitButton = document.getElementById("btnCompleteProductConfig");

        if (!option88El || !option89El || !option98El || !submitButton) {
            console.warn("One or more elements for tooltip updating are missing.");
            return;
        }

        const qty88 = Number(option88El.value) || 0;
        const qty89 = Number(option89El.value) || 0;
        const qty98 = Number(option98El.value) || 0;

        if (qty88 === 0 && qty89 === 0 && qty98 === 0) {
            submitButton.setAttribute("title", "Please select at least one device endpoint to continue.");
        } else {
            submitButton.setAttribute("title", "Click to continue.");
        }
    }

    // Integrate with existing functions
    function toggleSubmitButtonTooltip() {
        checkSubmitButtonState(); // Existing function to enable/disable button
        updateSubmitButtonTooltip();
    }

    document.addEventListener("DOMContentLoaded", function () {
        toggleSubmitButtonTooltip(); // Initial check

        // Add event listeners for changes in Config options 88, 89, and 98
        const option88El = document.getElementById("inputConfigOption88");
        const option89El = document.getElementById("inputConfigOption89");
        const option98El = document.getElementById("inputConfigOption98");

        if (option88El) {
            option88El.addEventListener("change", toggleSubmitButtonTooltip);
            option88El.addEventListener("keyup", toggleSubmitButtonTooltip);
        }

        if (option89El) {
            option89El.addEventListener("change", toggleSubmitButtonTooltip);
            option89El.addEventListener("keyup", toggleSubmitButtonTooltip);
        }

        if (option98El) {
            option98El.addEventListener("change", toggleSubmitButtonTooltip);
            option98El.addEventListener("keyup", toggleSubmitButtonTooltip);
        }
    });
</script>



