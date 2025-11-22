<?php
/* * ********************************************************************
*  Hide Domain Permissions from client area by WHMCS Services
*
* Created By WHMCSServices http://www.whmcsservices.com
* Contact Paul: dev@whmcsservices.com
*
* This software is furnished under a license and may be used and copied
* only in accordance with the terms of such license and with the
* inclusion of the above copyright notice. This software or any other
* copies thereof may not be provided or otherwise made available to any
* other person. No title to and ownership of the software is hereby
* transferred.
* ******************************************************************** */

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define addon module configuration parameters.
 * @return array
 */
function hidepermissions_config()
{
    return [
        'name' => 'Hide Domain Permissions',
        'description' => 'This module will hide domain permission from client area.',
        "author" => "<a href='https://www.whmcsservices.com/' target='_blank'><img width='100px' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARYAAABKCAMAAABJqItFAAAC31BMVEUAAAAjHyBMTE0wLS89Oz07OTtOTlC2HSUmIiPgHSU9Oz3qHSVBP0ElISLcHSWtHSWtHSU6ODkyMDHaHSWuHSXFHSVLSkw4NTfEHSWjHSUsKSo0MjPmHSUnJCVQUFKiHSVHR0jjHSVGRUbSHSUuKyxBQEGuHSXvHSXtHSWnHSU1MjT0HSXnHSW/HSWpHSXEHSVRUVOhHSVKSktQUFJDQkTkHSVOTlBSUlQ9OzyxHSVVVljMHSUkICHtHSW7HSVKSUtOTlAlISIoJCUnIyTXHSUmIiPBHSXNHSXGHSUvLC6oHSVSU1WlHSXyHSWoHSWwHSWmHSXKHSVPT1HVHSW5HSWkHSXwHSXOHSWgHSUnIySkHSXLHSWlHSXsHSXDHSXuHSU+PT/sHSXzHSXeHSUrKCnKHSU1MzT4HSU0MTPgHSXgHSVYWVveHSU1MzVIR0npHSWoHSUlISI+PT5AP0D0HSXeHSXLHSU+PD7iHSX3HSVXWFqzHSX2HSW/HSX+QEdMTE5CQUMjHyD////PHSXMHSVIR0lNTU8qJyjfHSU3NTc/Pj/THSVBQEIoJSZGRUdLS03hHSU9PD02MzWrHSXGHSUxLjDZHSVEREUvLS4sKSoyMDHBHSW6HSW4HSVKSUvtHSXlHSXEHSXnHSVPUFFDQkM7OTrxHSU5NzmhHSUmIyQ0MjM8OjzWHSWlHSWzHSUuKyytHSXIHSXsHSXcHSWeHSWnHSWkHSXzHSWvHSVTVFbVHSW9HSUlISKxHSVSUlRYWVtRUVPvHSXQHSWgHSVVVlhXV1n98fHpHSX9HSXwqq33HSXjcndubG3z8/Pn5ufQ0ND3x8mko6Tc29zz1dbKycqRkJDBKzO1HSX3nKC8vLz0uLvhnKCGhYbtfYHnVlzfMzv94+TsjpLYi494d3j6TlXLTlXAPEPMMjn1Ljb61da5uLmXlpf3jpLvZGlhYGHqR07GxsexsLHMcnfLZGn+KzMhvJ+gAAAAgXRSTlMAgEBAQBC/v0AQgEC/v0BAECC/gIBAv7+AQL+AIO+/v4CAv7+AMCBQMN/cv7+/r2Dv71Aw7+/Pv7+/o5+fj4BwYGBQMO/bz7+/n5+AcGBQ78+vj4+Pj4CAgHBgMDDv79/Xz6+vj4BwQO/fz7+/r5+f76+fj3BwcGBg79/P31DPr68JdP9TAAAM8ElEQVR42u3a919VdRzH8bf3cpWLCxTEAaEGMlSWYrnCnXtrqak5Uyubpu04KjhwgBNUHOBAzW1qlmmDJPfMlSNztOcf0Of7+X6/55wLZOtRv9z7+uGeg/R45OP5+HzP93tA+PLly5cvX758+fLly6sLDHkovkLs3m0LYuMhatuqPbw9d0Kf1cv2L1+xhVh2vzMYVPu0tMr+8ObCWk7bvtDGEgFRx7S0tFYueGvulrOnerJsaADquTQq3FsHZlKHzaVYuoMql8aVgxfmHrVzcWmWeFDt0mSt4HWFTZhRFkssKP80b3WJSZlfJssuyeKlLjEpq+7NohsEL8o9Ys29WAalWXnTyW7kjnuyjLaxdITX9OLKHWuuFt+4VgZLBKjKUsTLtumwoSt3XDAM44syWAYAcKXZCw+Cd9Rw3d1LpHKhrEXU0zy2/Ee7UROHw9EEVi4HZZd/rBYViP+9J/bc/dowio7IZ8utk0WXLZYkdfa3F+4C2tSoUaN8+fIOoG95ri/QRN61KS9Dk/u4vnzDif/oAVVfUEEtEvdxLZRMcrOPucQWUiZwQNKGDRveeeft3oPx93qYu/9fDMueS6Tyo3zkHqGxOWOxdAUQlFaiQcQyj6sL1JB3NYDUdK5bugwO8ZmfXx6OfFUikEqXg6L7ALRJPHhA1w1Ui31miW3EpCTt+kiyvP12xN+bmGnTpk2dOvVD/NPCPrsgVOROVEwqxauXnf/yUtFlwdKA34hkn5w7d+pcltyMHHkcseTJmgF1JRCJcXDMmydgiCVdBwg0JioPNNFcTJVK32QwTRWEwNhdFsvurv8rS+uviOJ7uUELlSMLV58vvr3isvHLAv6buMLfoxadO3727HHDOCZcghA1nSOW6SqghgQiMY7o5ByRj86BN5mKtdAs3YpGyYXEfHvdMHiXnWV34P/J8tpJw/hOnlu+ECr0yL1zfvmKX40vF+zmYSmkzh07l1tYmEsuN+mrtsBcrj4cc1VAfekTpZ0c4jMvj1jydMm85pRWE3mTmNpMUiU3kULNUhMVXFfJ8mi8ZKkFUYOewcHdq8KzkATcI3eV6tWrV3FDFxZdr96kita3Kw4cODA6DPZiaFiur2SWq0JF70RfEssAAEHhc+ZsPGukzxGdNYx0YmkFLOHqw2+JKgjKR19ZjGDqs4+erqZMRdVAqqTqaz6o1FQpO2KJYJZY1LJYevbetm3v3r3v9woWs1NhxYoVy5cPC+y0v3HjZdTqEKDTwoXbt28PsabF3fLddzdvXrx4cUsJEz1hPrVqVUo9NwM0Wrly5aFD69YNj4bVi8V0YJEsX9OduUGfMW4/K/7Pz2VkZJ82TmVkZMyZk33MuCh0agOVMjMLCgoqoW6Byg8FzNMUygkO+lATpXsBkoq16moqh6SaqKbKwcuR5kmxRGiW7kDwggWS5f0trwZqlsad9u9v3J9ZagILmcVtsgzpMJtZqA5DAEyaMUOyrFozwk0qQ3colnV7WsNsZJFxfR2z0CZ0yTy33DZObusJwJmTk3PKMA5miL41zs7kG2KZNWtWZmZTBGSq/KLEp5BSTvATn/aJovsoQcURi7ypK9gYSk1VKmrwVbMkaZZgVF1gsWypoFmW7yeWhzxZoFncHWabLDvHu1FlhsWyZgQwfIfFsucJ6N6gc5xkuW4Y10yWYuNWPABXx61b59LaWp9D/XDxZo4MCMjKmkUxD5fsx5fMSlBO8JPXSuSja6qoKGKRVMQiqeqrqXrcFeUQRSFCPVsCa3ENEM8sveKZZcvrdpYQZumMBszS2WR5aTazjOogWHZOwShmSRnFLGuiwz5llpHDmcUal6/ogcssP9LWbJ5ybxmXewUCGL106dJvieXo0q322iNg06ZNWVlZeDBLFTCZPogqAMop4Hl5JZZMs1R1ta2/uswmoIKWyJpOhmyA2oniG0C2gFkGoBezdLWzNFAsIczSx2TpI1lQnVlGYQaztEYKszxTkVmGowezNITuN9JglhuGcVWznC86c56X0CLqLLGcXbTUnhPJa7mouLVrN3EBBCWoApDl2YOgOdLxcMmp0usvwBqrxwtUlYIg6qk36KRH5SYkWYLRnFkq2FnALMMUyxiT5X4PlqfCJEs9NGKWRpJlBCp6ssQcPmkUfSVY6Kir34nOnym63R+AKy6X+tmgji6y54Tfek5dCCggQEIlQ3xusiIW5nncdOKpIhY1VYiqxL2AyZm6pqkQ9TbPLb3FwFT1ZOnlwdJJsKxGArMk/AHLziqeLCmSZWVMCZYnDt+9bhRd+P7qhSKLpfjMLfGcx9MbRVcM6tgnubaccM3k/GbqQp9WUFivqFTEIq/aiW9oiALU6oMVrTvd8y7Pw/+zPU2W5sGxzPK5ydIyYQj6MMuQmswS8icsT9UbzyyfKpah0eAslsN3v6OBOflFsV5Ed86cOb/8MQCTs2XHDep0ZvZGMxeQzXXJVs0MDZU8Lsy0EjyharJCQxVVgNISVwKys2gqni4Il97WKXcws+idyM7yEqiazBIiWdylWMKqcJKFWiVZMFTtRA0rwpb78OHP9vCz5VqR8bXYiY6IN+iHAASNmyk7aohOZVsBiMsVPtXERxzfhepvZZtUHLGocVJUY6USAtZzHizwiyMqTnkNMFmeDfwjFogSmCWhP7OgFIuqFMtAc4NuGAYrk2X+97SMTp4sKr6zelknUKHrdafYJX+m7nEA1egRk5tbLZeqJh5B48aJ241xwEZZnS4bNwqcUDiz+VpHUSkl1JnJ1YHLj4uCyPWCXoBx4GrF6nei7vdkCWGWmp0FS+e/zoJnrHNLD5i9bLLMuHbkxo0jd8ROFAJg7Fqr0zwuJtPTAJ5eKlokGk1EumpArgxOea0GJzNVaye54pQSiElUB356rGR+49QClE4I7KpY4hVL16oqDxYwS0tm6fNnLM9UqSgDED3UPM5FQ9eQWTx/xN0SgKvpJiteRidMpbEAIrdywiZy9FKzLoACglNDOSUPX9SVlFBHTRWxKSjl5IqTC9DvUblBo7tk6W3uRIObUxUqeLIMEyydOwmWMWWwVOfMnWhKIw6Ue6BmeQ261qVZhgWKTSHL3gnBkpWllKIADMoxaxeZs1UXCRCVCE55rSZumEcydZFcxCKZ6lhjpZwYjKjGahYkMctukyW4jEWExoKl02rBklAGS4kNup5cRG6emR4jJcseN1TRpVlqAoia5ZHYpL+ZZd8j/DPMnM4Mk2gQoO7glFC1xQ1R1cY4Zop0Kq1IyRRpjVUJpzomS8RfYelPLJRgCfmrLHqDxouS5Qmo3KVYeFgqZXokVtFRbfQCs8wxQ3uLyB9Qd3BKHmJR19o5YqraOZXW2KUcsegbnirL6W+y1LRY3H/EMr4kS4xicWsW3ciSLDwsBVT6qRPHrhRwxHLCNAqCqFA1JxywiPz1Fxl6nmrDX127ZIipciolzdYFkXr9yWfVaA3m7C5ZAhHLLLHwZCnxbAmxWIBS70RD5OEfniwjoFggWWKg61GShagxkd7Y8k/Twlkiu2Icyy9QvQXuPVVhZaBjoU56CRc9T8SirpHM49JKrgxZl3Dp40RtvobTH7CTs5ZkiYiQj9wIxDLLq1VjPV8VwQ0xWToDJd+gJ8lTbj+MZ5YJVcbLV0XFEj1QssDqZU8WuQ3RC/4pOvLPVR0/fnSJzg9cZfP3RvwrE4UkvRjIn51IzV9d2zGPqYTaPFW6jsAg8wvmAZKYxTy3INjj3PKYJwtMlj42ljDJsnkxs4Shuse5pSJG2n/eMgJWAz1ZXgeQPJ2i3een6dzcb04fmKubiBIs5YBy+r4yAHULf0lFLPrqqYT24XOswtuLV1O+VVRtgcF2FvHDwt42lv4owdJJs4yxsaCfjaU6gAk2ltZAjJ0lGlbulz1YQE3Moy7Si1BeHqn8cOLn6WZNgyAbrSnaAe3KZuHIQV35O5YSuViLryOpkEvlwkLNRCrkkmSyRDQAEBivWUilJEtjzZJgZyEXzSJU4B6lWUiFqji89CmX62FneRhQvxM7eoyes+dufvvT8Zt5Vm2g8i+nIqcgfU9EULf6D9uqm7byO/76S2ZoWzmcqN6r3BYq/1bhPEuDXOACu0cIlqT4npA9NqB5r729mvcXSEh4iIMspKZKfG/MmEdEoIb0u7/D5g5P9QuDLKbfUynzUxq11l/3eKbhoUPDG74YBs8a2lj6gUrnfrh4nAbm4pV5tibCewobarGwbz6VXlbNvOpf50ZbLCGgDuaXnXepAD08WQ6oDtrzPhWgtQdLs322Duge8DoVYIpieQlUi4+5fR71hTc2JcV65Lb5xOxj1SsOeGcxE8xzi+sDM4mT2M0LF5CueofZU4dB1OIDe694Mwrlbjl1Wggoh82kRTK8PveYBDkuT4padEv2ln9u6suXL1++fPny5cuXL+/rd/0dtIDpyDvIAAAAAElFTkSuQmCC'></a>",
        'language' => 'english',
        'version' => '1.0.0',
        'fields' => []
    ];
}

/**
 * Activate.
 * @return array Optional success/failure message
 */
function hidepermissions_activate()
{
    // Create custom tables and schema required by your module
    try {

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Hide Domain Permissions is activated.',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to activate: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate.
 * @return array Optional success/failure message
 */
function hidepermissions_deactivate()
{
    // Undo any database and schema modifications made by your module here
    try {

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'Hide Domain Permissions Deactivated Successfully.',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            "status" => "error",
            "description" => "Unable to deactivate module: {$e->getMessage()}",
        ];
    }
}

/**
 * Admin Area Output.
 * @return string
 */
function hidepermissions_output($vars)
{
    echo "Nothing for admin";
}
