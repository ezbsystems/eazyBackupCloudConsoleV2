
<script type="text/template" id="expandedServiceTemplate">
    <tr class="cometuserDetails">
        <td colspan="10">
            <div id="service-data" style="display:none">
                <!-- Here you might have placeholders or special markers for data: -->
                <div class="pt-10" data-serviceid="{{serviceId}}">
                    <div class="container mx-auto px-4">

                        <!-- Profile Header -->
                        <div class="flex justify-between items-center bg-slate-100 p-4 rounded-md shadow">
                            <div class="flex items-center">
                                <h3 class="text-lg text-gray-800 mr-2">Profile</h3>
                                ...
                            </div>
                            <div class="flex space-x-2">
                                <button type="button" class="text-sm text-green-700 hover:text-green-600 px-4 py-2 rounded save_changes_btn" id="saveprofile">
                                    <i class="fa fa-save mr-2"></i> Save changes
                                </button>
                                ...
                            </div>
                        </div>
                        
                        <!-- Example placeholders -->
                        <div class="mb-4 flex justify-between">
                           <label class="text-sm text-gray-700">Account Name:</label>
                           <input type="text" class="text-sm form-input" name="accountname" 
                                  placeholder="Account Name" value="{{accountName}}">
                        </div>
                        
                        ...
                    </div>
                </div>
            </div>
        </td>
    </tr>
</script>
