<?php

# Define $assetLogoPath 

function custom_base_img_path_hook($vars) 
{
   return array("assetLogoPath" => $vars['BASE_PATH_IMG']."/logo.svg");
}
add_hook("ClientAreaPage", 1, "custom_base_img_path_hook");
?>