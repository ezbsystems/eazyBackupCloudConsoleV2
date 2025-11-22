<!-- Update Email Modal -->
<div id="update-email-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Update Email Address</h2>
            <button id="close-modal" class="text-gray-600 hover:text-gray-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="update-email-form">
            <input type="hidden" id="update-email-id" name="emailId">
            <div class="mb-4">
                <label for="update-email-address" class="block text-gray-700">New Email Address</label>
                <input type="email" id="update-email-address" name="email" class="mt-1 block w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <div id="invalid_email_update" class="mt-2 text-red-500 text-sm"></div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" id="close-modal" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" id="updateemaildata" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Save</button>
            </div>
        </form>
    </div>
</div>