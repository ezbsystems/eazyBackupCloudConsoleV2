<!-- accounts\templates\eazyBackup\includes\generate-password.tpl -->
<div id="modalGeneratePassword" class="hidden">
    <form action="#" id="frmGeneratePassword" class="bg-white rounded-lg shadow-lg mt-4">
        <div class="bg-indigo-600 text-white px-4 py-3 rounded-t-lg flex items-center justify-between">
            <h4 class="text-lg font-semibold">{lang key='generatePassword.title'}</h4>
            <button type="button" class="text-white hover:text-gray-200" onclick="toggleModal('modalGeneratePassword')">
                &times;
            </button>
        </div>
        <div class="px-6 py-4">
            <div id="generatePwLengthError" class="hidden bg-red-100 text-red-700 border border-red-300 p-3 rounded mb-4">
                {lang key='generatePassword.lengthValidationError'}
            </div>

            <!-- Password Length Input -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center mb-4">
                <label for="inputGeneratePasswordLength" class="text-sm font-medium text-gray-700">
                    {lang key='generatePassword.pwLength'}
                </label>
                <div class="sm:col-span-2">
                    <input 
                        type="number" 
                        min="8" 
                        max="64" 
                        value="12" 
                        step="1" 
                        id="inputGeneratePasswordLength" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                    />
                </div>
            </div>

            <!-- Generated Password Output -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center mb-4">
                <label for="inputGeneratePasswordOutput" class="text-sm font-medium text-gray-700">
                    {lang key='generatePassword.generatedPw'}
                </label>
                <div class="sm:col-span-2">
                    <input 
                        type="text" 
                        id="inputGeneratePasswordOutput" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                        readonly
                    />
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end space-x-4">
                <button 
                    type="submit" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                >
                    <i class="fas fa-plus fa-fw"></i>
                    {lang key='generatePassword.generateNew'}
                </button>
                <button 
                    type="button" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 copy-to-clipboard"
                    data-clipboard-target="#inputGeneratePasswordOutput"
                >
                    <img src="{$WEB_ROOT}/assets/img/clippy.svg" alt="Copy to clipboard" width="15" class="mr-2">
                    {lang key='copy'}
                </button>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-4">
            <button 
                type="button" 
                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                onclick="toggleModal('modalGeneratePassword')"
            >
                {lang key='close'}
            </button>
            <button 
                type="button" 
                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                id="btnGeneratePasswordInsert" 
                data-clipboard-target="#inputGeneratePasswordOutput"
            >
                {lang key='generatePassword.copyAndInsert'}
            </button>
        </div>
    </form>
</div>

<script>
    function toggleModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.toggle('hidden');
        const targetButton = document.querySelector("#btnGeneratePasswordInsert");
        targetButton.scrollIntoView({ behavior: "smooth", block: "end" });
    }
</script>
