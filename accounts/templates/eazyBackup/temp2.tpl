<!-- Modal Manage Vault -->
<div id="manage-vault-modal"     
     class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 hidden" 
     style="display: none;"  
>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative">
        <!-- Close Button -->
        <button 
          class="close absolute top-4 right-4 text-gray-500 hover:text-gray-700 focus:outline-none"
          type="button"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" 
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" 
                      stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Manage Storage Vault</h2>
        </div>

        <!-- Modal Body -->
        <form class="space-y-4" method="post" action="#">
            <input type="hidden" id="vault_storageID" name="vault_storageID" value="">

            <!-- Storage Vault Name -->
            <div class="flex flex-col">
                <label for="storagename" class="text-gray-700 font-medium mb-1">Name:</label>
                <input 
                  type="text" 
                  id="storagename" 
                  name="storagename" 
                  placeholder="Enter Storage Vault Name"
                  class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                  required
                >
            </div>

            <!-- Quota -->
            <div class="flex flex-col">
                <label for="storageSize" class="text-gray-700 font-medium mb-1">Quota:</label>
                <div class="flex items-center space-x-2">
                    <input 
                      type="number" 
                      id="storageSize" 
                      name="storageSize" 
                      placeholder="1" 
                      min="1" 
                      max="999"
                      class="border border-gray-300 rounded-md px-3 py-2 w-20 bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <select 
                      id="standardSize" 
                      name="standardSize" 
                      class="border border-gray-300 rounded-md px-3 py-2 bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="GB">GB</option>
                        <option value="TB">TB</option>
                    </select>
                    <label class="inline-flex items-center space-x-2">
                        <input 
                          type="checkbox" 
                          id="storageUnlimited" 
                          name="storageUnlimited" 
                          class="form-checkbox h-5 w-5 text-blue-600"
                        >
                        <span class="text-gray-700">Unlimited</span>
                    </label>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-2">
                <button 
                  type="submit" 
                  id="manageVaultrequest" 
                  class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md"
                >
                    Save Changes
                </button>
                <button 
                  type="button" 
                  class="close bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md"
                >
                    Close
                </button>
            </div>
        </form>
    </div>
</div>
