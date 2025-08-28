<?php
/**
 * MSPBranding - Utility class for MSP branding across the system
 * 
 * Provides easy access to MSP company information and branding
 * for use in customer portals, invoices, emails, etc.
 */

use WHMCS\Database\Capsule;

class MSPBranding
{
    /**
     * Get MSP company branding information
     * 
     * @param int $mspId MSP ID
     * @return array Branding information
     */
    public static function getMspBranding($mspId)
    {
        try {
            $companyProfile = Capsule::table('msp_reseller_company_profile')
                ->where('msp_id', $mspId)
                ->first();

            if (!$companyProfile) {
                return [
                    'company_name' => 'Your Company',
                    'contact_email' => '',
                    'phone' => '',
                    'website' => '',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => '',
                    'logo_url' => null,
                    'has_logo' => false
                ];
            }

            // Build logo URL
            $logoUrl = null;
            $hasLogo = false;
            if ($companyProfile->logo_filename) {
                $logoPath = __DIR__ . '/../uploads/logos/' . $companyProfile->logo_filename;
                if (file_exists($logoPath)) {
                    $logoUrl = '/modules/addons/mspconnect/uploads/logos/' . $companyProfile->logo_filename;
                    $hasLogo = true;
                }
            }

            return [
                'company_name' => $companyProfile->company_name ?: 'Your Company',
                'contact_email' => $companyProfile->contact_email ?: '',
                'phone' => $companyProfile->phone ?: '',
                'website' => $companyProfile->website ?: '',
                'address' => $companyProfile->address ?: '',
                'city' => $companyProfile->city ?: '',
                'state' => $companyProfile->state ?: '',
                'postal_code' => $companyProfile->postal_code ?: '',
                'country' => $companyProfile->country ?: '',
                'logo_url' => $logoUrl,
                'has_logo' => $hasLogo,
                'full_address' => self::formatFullAddress($companyProfile)
            ];

        } catch (Exception $e) {
            error_log('MSPBranding: Error getting branding info: ' . $e->getMessage());
            return [
                'company_name' => 'Your Company',
                'contact_email' => '',
                'phone' => '',
                'website' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'country' => '',
                'logo_url' => null,
                'has_logo' => false,
                'full_address' => ''
            ];
        }
    }

    /**
     * Format full address from company profile
     * 
     * @param object $companyProfile Company profile object
     * @return string Formatted address
     */
    private static function formatFullAddress($companyProfile)
    {
        $addressParts = [];
        
        if ($companyProfile->address) {
            $addressParts[] = $companyProfile->address;
        }
        
        $cityStateParts = [];
        if ($companyProfile->city) {
            $cityStateParts[] = $companyProfile->city;
        }
        if ($companyProfile->state) {
            $cityStateParts[] = $companyProfile->state;
        }
        if ($companyProfile->postal_code) {
            $cityStateParts[] = $companyProfile->postal_code;
        }
        
        if (!empty($cityStateParts)) {
            $addressParts[] = implode(', ', $cityStateParts);
        }
        
        if ($companyProfile->country) {
            $addressParts[] = $companyProfile->country;
        }
        
        return implode("\n", $addressParts);
    }

    /**
     * Generate HTML for displaying company logo
     * 
     * @param int $mspId MSP ID
     * @param array $options Options for logo display
     * @return string HTML for logo
     */
    public static function getLogoHtml($mspId, $options = [])
    {
        $branding = self::getMspBranding($mspId);
        
        $maxWidth = $options['max_width'] ?? '200px';
        $maxHeight = $options['max_height'] ?? '80px';
        $class = $options['class'] ?? '';
        $alt = $options['alt'] ?? $branding['company_name'] . ' Logo';

        if (!$branding['has_logo']) {
            // Return company name as fallback
            return '<div class="' . htmlspecialchars($class) . '" style="max-width: ' . htmlspecialchars($maxWidth) . '; max-height: ' . htmlspecialchars($maxHeight) . '; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #374151;">
                ' . htmlspecialchars($branding['company_name']) . '
            </div>';
        }

        return '<img src="' . htmlspecialchars($branding['logo_url']) . '" 
                     alt="' . htmlspecialchars($alt) . '" 
                     class="' . htmlspecialchars($class) . '"
                     style="max-width: ' . htmlspecialchars($maxWidth) . '; max-height: ' . htmlspecialchars($maxHeight) . '; height: auto; width: auto;">';
    }

    /**
     * Generate company information block for emails/invoices
     * 
     * @param int $mspId MSP ID
     * @param array $options Display options
     * @return string HTML for company info
     */
    public static function getCompanyInfoHtml($mspId, $options = [])
    {
        $branding = self::getMspBranding($mspId);
        $includeAddress = $options['include_address'] ?? true;
        $includeContact = $options['include_contact'] ?? true;
        
        $html = '<div class="company-info">';
        
        // Company name
        $html .= '<div style="font-weight: bold; font-size: 18px; margin-bottom: 10px;">' . 
                htmlspecialchars($branding['company_name']) . '</div>';
        
        // Address
        if ($includeAddress && $branding['full_address']) {
            $html .= '<div style="margin-bottom: 10px;">' . 
                    nl2br(htmlspecialchars($branding['full_address'])) . '</div>';
        }
        
        // Contact information
        if ($includeContact) {
            if ($branding['phone']) {
                $html .= '<div style="margin-bottom: 5px;">Phone: ' . 
                        htmlspecialchars($branding['phone']) . '</div>';
            }
            
            if ($branding['contact_email']) {
                $html .= '<div style="margin-bottom: 5px;">Email: ' . 
                        htmlspecialchars($branding['contact_email']) . '</div>';
            }
            
            if ($branding['website']) {
                $html .= '<div style="margin-bottom: 5px;">Website: ' . 
                        '<a href="' . htmlspecialchars($branding['website']) . '">' . 
                        htmlspecialchars($branding['website']) . '</a></div>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get email template variables for an MSP
     * 
     * @param int $mspId MSP ID
     * @return array Template variables
     */
    public static function getEmailTemplateVars($mspId)
    {
        $branding = self::getMspBranding($mspId);
        
        return [
            'msp_company_name' => $branding['company_name'],
            'msp_contact_email' => $branding['contact_email'],
            'msp_phone' => $branding['phone'],
            'msp_website' => $branding['website'],
            'msp_address' => $branding['address'],
            'msp_city' => $branding['city'],
            'msp_state' => $branding['state'],
            'msp_postal_code' => $branding['postal_code'],
            'msp_country' => $branding['country'],
            'msp_full_address' => $branding['full_address'],
            'msp_logo_url' => $branding['logo_url'] ? $_SERVER['HTTP_HOST'] . $branding['logo_url'] : '',
            'msp_has_logo' => $branding['has_logo'] ? 'yes' : 'no'
        ];
    }
} 