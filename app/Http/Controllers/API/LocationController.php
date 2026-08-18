<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    /**
     * Get all states
     */
    public function getStates()
    {
        try {
            // Check if recruitment_state table exists
            $states = DB::table('recruitment_state')
                ->select('State_id', 'StateName', 'StateCode')
                ->orderBy('StateName')
                ->get();
            
            if ($states->isEmpty()) {
                // Fallback states if table is empty
                $fallbackStates = $this->getFallbackStates();
                
                return response()->json([
                    'success' => true,
                    'data' => $fallbackStates,
                    'total' => count($fallbackStates)
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $states,
                'total' => $states->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch states: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get local governments by state code or ID
     */
    public function getLocalGovernments($stateCode)
    {
        try {
            // First, get the state by various possible inputs
            $state = DB::table('recruitment_state')
                ->where('StateCode', $stateCode)
                ->orWhere('StateName', $stateCode)
                ->orWhere('State_id', $stateCode)
                ->first();
            
            if (!$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'State not found with code or ID: ' . $stateCode
                ], 404);
            }
            
            // Try to get LGAs from database first
            // Since the recruitment_local_gov table might not have a direct state relationship,
            // we'll try multiple approaches or use fallback data
            
            $lgas = collect();
            
            // Attempt 1: Try to get LGAs by LocalGovCode prefix (e.g., NG-LA001 for Lagos)
            try {
                $lgas = DB::table('recruitment_local_gov')
                    ->where('LocalGovCode', 'LIKE', $state->StateCode . '%')
                    ->get(['loclGov_id', 'LocalGovName', 'LocalGovCode']);
            } catch (\Exception $e) {
                // Table might not exist or have different structure
                Log::warning('Could not query recruitment_local_gov table: ' . $e->getMessage());
            }
            
            // If no LGAs found in database, use fallback data
            if ($lgas->isEmpty()) {
                $fallbackLgas = $this->getFallbackLgas($state->StateName, $state->StateCode);
                
                if (!empty($fallbackLgas)) {
                    return response()->json([
                        'success' => true,
                        'state' => [
                            'State_id' => $state->State_id,
                            'StateName' => $state->StateName,
                            'StateCode' => $state->StateCode
                        ],
                        'data' => $fallbackLgas,
                        'total' => count($fallbackLgas),
                        'source' => 'fallback'
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'state' => [
                        'State_id' => $state->State_id,
                        'StateName' => $state->StateName,
                        'StateCode' => $state->StateCode
                    ],
                    'data' => [],
                    'total' => 0,
                    'message' => 'No LGAs found for this state'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'state' => [
                    'State_id' => $state->State_id,
                    'StateName' => $state->StateName,
                    'StateCode' => $state->StateCode
                ],
                'data' => $lgas,
                'total' => $lgas->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch local governments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fallback states if database table is empty
     */
    private function getFallbackStates()
    {
        return [
            ['State_id' => 1, 'StateName' => 'Abia', 'StateCode' => 'NG-AB'],
            ['State_id' => 2, 'StateName' => 'Adamawa', 'StateCode' => 'NG-AD'],
            ['State_id' => 3, 'StateName' => 'Akwa Ibom', 'StateCode' => 'NG-AK'],
            ['State_id' => 4, 'StateName' => 'Anambra', 'StateCode' => 'NG-AN'],
            ['State_id' => 5, 'StateName' => 'Bauchi', 'StateCode' => 'NG-BA'],
            ['State_id' => 6, 'StateName' => 'Bayelsa', 'StateCode' => 'NG-BY'],
            ['State_id' => 7, 'StateName' => 'Benue', 'StateCode' => 'NG-BE'],
            ['State_id' => 8, 'StateName' => 'Borno', 'StateCode' => 'NG-BO'],
            ['State_id' => 9, 'StateName' => 'Cross River', 'StateCode' => 'NG-CR'],
            ['State_id' => 10, 'StateName' => 'Delta', 'StateCode' => 'NG-DE'],
            ['State_id' => 11, 'StateName' => 'Ebonyi', 'StateCode' => 'NG-EB'],
            ['State_id' => 12, 'StateName' => 'Edo', 'StateCode' => 'NG-ED'],
            ['State_id' => 13, 'StateName' => 'Ekiti', 'StateCode' => 'NG-EK'],
            ['State_id' => 14, 'StateName' => 'Enugu', 'StateCode' => 'NG-EN'],
            ['State_id' => 15, 'StateName' => 'Abuja Federal Capital Territory', 'StateCode' => 'NG-FC'],
            ['State_id' => 16, 'StateName' => 'Gombe', 'StateCode' => 'NG-GO'],
            ['State_id' => 17, 'StateName' => 'Imo', 'StateCode' => 'NG-IM'],
            ['State_id' => 18, 'StateName' => 'Jigawa', 'StateCode' => 'NG-JI'],
            ['State_id' => 19, 'StateName' => 'Kaduna', 'StateCode' => 'NG-KD'],
            ['State_id' => 20, 'StateName' => 'Kano', 'StateCode' => 'NG-KN'],
            ['State_id' => 21, 'StateName' => 'Katsina', 'StateCode' => 'NG-KT'],
            ['State_id' => 22, 'StateName' => 'Kebbi', 'StateCode' => 'NG-KE'],
            ['State_id' => 23, 'StateName' => 'Kogi', 'StateCode' => 'NG-KO'],
            ['State_id' => 24, 'StateName' => 'Kwara', 'StateCode' => 'NG-KW'],
            ['State_id' => 25, 'StateName' => 'Lagos', 'StateCode' => 'NG-LA'],
            ['State_id' => 26, 'StateName' => 'Nasarawa', 'StateCode' => 'NG-NA'],
            ['State_id' => 27, 'StateName' => 'Niger', 'StateCode' => 'NG-NI'],
            ['State_id' => 28, 'StateName' => 'Ogun', 'StateCode' => 'NG-OG'],
            ['State_id' => 29, 'StateName' => 'Ondo', 'StateCode' => 'NG-ON'],
            ['State_id' => 30, 'StateName' => 'Osun', 'StateCode' => 'NG-OS'],
            ['State_id' => 31, 'StateName' => 'Oyo', 'StateCode' => 'NG-OY'],
            ['State_id' => 32, 'StateName' => 'Plateau', 'StateCode' => 'NG-PL'],
            ['State_id' => 33, 'StateName' => 'Rivers', 'StateCode' => 'NG-RI'],
            ['State_id' => 34, 'StateName' => 'Sokoto', 'StateCode' => 'NG-SO'],
            ['State_id' => 35, 'StateName' => 'Taraba', 'StateCode' => 'NG-TA'],
            ['State_id' => 36, 'StateName' => 'Yobe', 'StateCode' => 'NG-YO'],
            ['State_id' => 37, 'StateName' => 'Zamfara', 'StateCode' => 'NG-ZA'],
        ];
    }

    /**
     * Fallback LGAs for common states
     */
    private function getFallbackLgas($stateName, $stateCode)
    {
        $lgasMap = [
            'Lagos' => [
                ['loclGov_id' => 1, 'LocalGovName' => 'Agege', 'LocalGovCode' => 'NG-LA001'],
                ['loclGov_id' => 2, 'LocalGovName' => 'Ajeromi-Ifelodun', 'LocalGovCode' => 'NG-LA002'],
                ['loclGov_id' => 3, 'LocalGovName' => 'Alimosho', 'LocalGovCode' => 'NG-LA003'],
                ['loclGov_id' => 4, 'LocalGovName' => 'Amuwo-Odofin', 'LocalGovCode' => 'NG-LA004'],
                ['loclGov_id' => 5, 'LocalGovName' => 'Apapa', 'LocalGovCode' => 'NG-LA005'],
                ['loclGov_id' => 6, 'LocalGovName' => 'Badagry', 'LocalGovCode' => 'NG-LA006'],
                ['loclGov_id' => 7, 'LocalGovName' => 'Epe', 'LocalGovCode' => 'NG-LA007'],
                ['loclGov_id' => 8, 'LocalGovName' => 'Eti-Osa', 'LocalGovCode' => 'NG-LA008'],
                ['loclGov_id' => 9, 'LocalGovName' => 'Ibeju-Lekki', 'LocalGovCode' => 'NG-LA009'],
                ['loclGov_id' => 10, 'LocalGovName' => 'Ifako-Ijaiye', 'LocalGovCode' => 'NG-LA010'],
                ['loclGov_id' => 11, 'LocalGovName' => 'Ikeja', 'LocalGovCode' => 'NG-LA011'],
                ['loclGov_id' => 12, 'LocalGovName' => 'Ikorodu', 'LocalGovCode' => 'NG-LA012'],
                ['loclGov_id' => 13, 'LocalGovName' => 'Kosofe', 'LocalGovCode' => 'NG-LA013'],
                ['loclGov_id' => 14, 'LocalGovName' => 'Lagos Island', 'LocalGovCode' => 'NG-LA014'],
                ['loclGov_id' => 15, 'LocalGovName' => 'Lagos Mainland', 'LocalGovCode' => 'NG-LA015'],
                ['loclGov_id' => 16, 'LocalGovName' => 'Mushin', 'LocalGovCode' => 'NG-LA016'],
                ['loclGov_id' => 17, 'LocalGovName' => 'Ojo', 'LocalGovCode' => 'NG-LA017'],
                ['loclGov_id' => 18, 'LocalGovName' => 'Oshodi-Isolo', 'LocalGovCode' => 'NG-LA018'],
                ['loclGov_id' => 19, 'LocalGovName' => 'Shomolu', 'LocalGovCode' => 'NG-LA019'],
                ['loclGov_id' => 20, 'LocalGovName' => 'Surulere', 'LocalGovCode' => 'NG-LA020'],
            ],
            'Abuja Federal Capital Territory' => [
                ['loclGov_id' => 1, 'LocalGovName' => 'Abaji', 'LocalGovCode' => 'NG-FC001'],
                ['loclGov_id' => 2, 'LocalGovName' => 'Abuja Municipal', 'LocalGovCode' => 'NG-FC002'],
                ['loclGov_id' => 3, 'LocalGovName' => 'Bwari', 'LocalGovCode' => 'NG-FC003'],
                ['loclGov_id' => 4, 'LocalGovName' => 'Gwagwalada', 'LocalGovCode' => 'NG-FC004'],
                ['loclGov_id' => 5, 'LocalGovName' => 'Kuje', 'LocalGovCode' => 'NG-FC005'],
                ['loclGov_id' => 6, 'LocalGovName' => 'Kwali', 'LocalGovCode' => 'NG-FC006'],
            ],
            'Rivers' => [
                ['loclGov_id' => 1, 'LocalGovName' => 'Abua/Odual', 'LocalGovCode' => 'NG-RI001'],
                ['loclGov_id' => 2, 'LocalGovName' => 'Ahoada East', 'LocalGovCode' => 'NG-RI002'],
                ['loclGov_id' => 3, 'LocalGovName' => 'Ahoada West', 'LocalGovCode' => 'NG-RI003'],
                ['loclGov_id' => 4, 'LocalGovName' => 'Akuku-Toru', 'LocalGovCode' => 'NG-RI004'],
                ['loclGov_id' => 5, 'LocalGovName' => 'Andoni', 'LocalGovCode' => 'NG-RI005'],
                ['loclGov_id' => 6, 'LocalGovName' => 'Asari-Toru', 'LocalGovCode' => 'NG-RI006'],
                ['loclGov_id' => 7, 'LocalGovName' => 'Bonny', 'LocalGovCode' => 'NG-RI007'],
                ['loclGov_id' => 8, 'LocalGovName' => 'Degema', 'LocalGovCode' => 'NG-RI008'],
                ['loclGov_id' => 9, 'LocalGovName' => 'Eleme', 'LocalGovCode' => 'NG-RI009'],
                ['loclGov_id' => 10, 'LocalGovName' => 'Emohua', 'LocalGovCode' => 'NG-RI010'],
                ['loclGov_id' => 11, 'LocalGovName' => 'Etche', 'LocalGovCode' => 'NG-RI011'],
                ['loclGov_id' => 12, 'LocalGovName' => 'Gokana', 'LocalGovCode' => 'NG-RI012'],
                ['loclGov_id' => 13, 'LocalGovName' => 'Ikwerre', 'LocalGovCode' => 'NG-RI013'],
                ['loclGov_id' => 14, 'LocalGovName' => 'Khana', 'LocalGovCode' => 'NG-RI014'],
                ['loclGov_id' => 15, 'LocalGovName' => 'Obio/Akpor', 'LocalGovCode' => 'NG-RI015'],
                ['loclGov_id' => 16, 'LocalGovName' => 'Ogba/Egbema/Ndoni', 'LocalGovCode' => 'NG-RI016'],
                ['loclGov_id' => 17, 'LocalGovName' => 'Ogu/Bolo', 'LocalGovCode' => 'NG-RI017'],
                ['loclGov_id' => 18, 'LocalGovName' => 'Okrika', 'LocalGovCode' => 'NG-RI018'],
                ['loclGov_id' => 19, 'LocalGovName' => 'Omuma', 'LocalGovCode' => 'NG-RI019'],
                ['loclGov_id' => 20, 'LocalGovName' => 'Opobo/Nkoro', 'LocalGovCode' => 'NG-RI020'],
                ['loclGov_id' => 21, 'LocalGovName' => 'Oyigbo', 'LocalGovCode' => 'NG-RI021'],
                ['loclGov_id' => 22, 'LocalGovName' => 'Port Harcourt', 'LocalGovCode' => 'NG-RI022'],
                ['loclGov_id' => 23, 'LocalGovName' => 'Tai', 'LocalGovCode' => 'NG-RI023'],
            ],
            'Kano' => [
                ['loclGov_id' => 1, 'LocalGovName' => 'Ajingi', 'LocalGovCode' => 'NG-KN001'],
                ['loclGov_id' => 2, 'LocalGovName' => 'Albasu', 'LocalGovCode' => 'NG-KN002'],
                ['loclGov_id' => 3, 'LocalGovName' => 'Bagwai', 'LocalGovCode' => 'NG-KN003'],
                ['loclGov_id' => 4, 'LocalGovName' => 'Bebeji', 'LocalGovCode' => 'NG-KN004'],
                ['loclGov_id' => 5, 'LocalGovName' => 'Bichi', 'LocalGovCode' => 'NG-KN005'],
                ['loclGov_id' => 6, 'LocalGovName' => 'Bunkure', 'LocalGovCode' => 'NG-KN006'],
                ['loclGov_id' => 7, 'LocalGovName' => 'Dala', 'LocalGovCode' => 'NG-KN007'],
                ['loclGov_id' => 8, 'LocalGovName' => 'Dambatta', 'LocalGovCode' => 'NG-KN008'],
                ['loclGov_id' => 9, 'LocalGovName' => 'Dawakin Kudu', 'LocalGovCode' => 'NG-KN009'],
                ['loclGov_id' => 10, 'LocalGovName' => 'Dawakin Tofa', 'LocalGovCode' => 'NG-KN010'],
                ['loclGov_id' => 11, 'LocalGovName' => 'Doguwa', 'LocalGovCode' => 'NG-KN011'],
                ['loclGov_id' => 12, 'LocalGovName' => 'Fagge', 'LocalGovCode' => 'NG-KN012'],
                ['loclGov_id' => 13, 'LocalGovName' => 'Gabasawa', 'LocalGovCode' => 'NG-KN013'],
                ['loclGov_id' => 14, 'LocalGovName' => 'Garko', 'LocalGovCode' => 'NG-KN014'],
                ['loclGov_id' => 15, 'LocalGovName' => 'Garun Mallam', 'LocalGovCode' => 'NG-KN015'],
                ['loclGov_id' => 16, 'LocalGovName' => 'Gaya', 'LocalGovCode' => 'NG-KN016'],
                ['loclGov_id' => 17, 'LocalGovName' => 'Gezawa', 'LocalGovCode' => 'NG-KN017'],
                ['loclGov_id' => 18, 'LocalGovName' => 'Gwale', 'LocalGovCode' => 'NG-KN018'],
                ['loclGov_id' => 19, 'LocalGovName' => 'Gwarzo', 'LocalGovCode' => 'NG-KN019'],
                ['loclGov_id' => 20, 'LocalGovName' => 'Kabo', 'LocalGovCode' => 'NG-KN020'],
            ],
        ];
        
        // Try to match by state name
        foreach ($lgasMap as $key => $lgas) {
            if (str_contains($stateName, $key) || str_contains($key, $stateName)) {
                return $lgas;
            }
        }
        
        // Try to match by state code
        $codeMap = [
            'NG-LA' => 'Lagos',
            'NG-FC' => 'Abuja Federal Capital Territory',
            'NG-RI' => 'Rivers',
            'NG-KN' => 'Kano',
        ];
        
        if (isset($codeMap[$stateCode])) {
            return $lgasMap[$codeMap[$stateCode]];
        }
        
        return [];
    }

    /**
     * Get all local governments (optional, for admin use)
     */
    public function getAllLocalGovernments()
    {
        try {
            $lgas = DB::table('recruitment_local_gov')->get();
            
            return response()->json([
                'success' => true,
                'data' => $lgas,
                'total' => $lgas->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch local governments: ' . $e->getMessage()
            ], 500);
        }
    }
}