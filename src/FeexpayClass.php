<?php

declare(strict_types=1);

namespace Feexpay\FeexpayPhp;

class FeexpayClass
{
    /**
     * Create a new Skeleton Instance
     * @param $id
     * @param $token
     * @param $callback_url
     * @param $error_callback_url
     * @param $mode
     */

    public function __construct($id,$token,$callback_url,$mode='LIVE', $error_callback_url = '')
    {
        // constructor body
        $this->id = $id;
        $this->token = $token;
        $this->callback_url = $callback_url;
        $this->error_callback_url = $error_callback_url;
        $this->mode = $mode;

    }

    public function init($amount, $componentId, $use_custom_button = false, $custom_button_id = "",  $description = "", $callback_info=""){
        $token = $this->token;
        $id = $this->id;
        $callback_url = $this->callback_url;
        $error_callback_url = $this->error_callback_url;

        echo "
        <script src='https://api.feexpay.me/feexpay-javascript-sdk/index.js'></script>
        <script type='text/javascript'>

        FeexPayButton.init('$componentId',{
             id:'$id',
             amount:$amount,
             token:'$token',
             callback_url:'$callback_url',
             mode: 'LIVE',
             custom_button: '$use_custom_button',
            id_custom_button: '$custom_button_id',
            description: '$description',
            callback_info: '$callback_info',
            error_callback_url: '$error_callback_url',
         })
        </script>";
    }

    public function getIdAndMarchanName()
    {
        try {
            $curl = curl_init("https://api.feexpay.me/api/shop/$this->id/get_shop");
            curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $responseCurl = curl_exec($curl);
            $responseData = json_decode($responseCurl);
            curl_close($curl);
            return $responseData;
        } catch (\Throwable $th) {
            echo "Id Request not send";
        }
    }

    public function paiementLocal(float $amount, string $phoneNumber, string $operatorName, string $fullname, string $email, string $callback_info, string $custom_id, string $otp="")
    {
        function curl_post($url, array $post = null, array $options = array())
        {
            $defaults = array(

                CURLOPT_POST => 1,

                CURLOPT_HEADER => 0,

                CURLOPT_URL => $url,

                CURLOPT_FRESH_CONNECT => 1,

                CURLOPT_RETURNTRANSFER => 1,

                CURLOPT_FORBID_REUSE => 1,

                //CURLOPT_TIMEOUT => 4,

                CURLOPT_POSTFIELDS => http_build_query($post),
                CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt',

            );

            $ch = curl_init();

            curl_setopt_array($ch, ($options + $defaults));

            if (!$result = curl_exec($ch)) {

                trigger_error(curl_error($ch));

            }

            curl_close($ch);

            return $result;

        }

        $responseIdGet = $this->getIdAndMarchanName();
        $nameMarchandExist = isset($responseIdGet->name);

        try {
            $post = array("phoneNumber" => $phoneNumber, "amount" => $amount, "reseau" => $operatorName, "token" => $this->token, "shop" => $this->id, "first_name" => $fullname, "email" => $email, "callback_info" => $callback_info, "reference" => $custom_id, "otp" =>$otp);
            $responseCurlPostPaiement = curl_post("https://api.feexpay.me/api/transactions/requesttopay/integration", $post);
            $responseCurlPostPaiementData = json_decode($responseCurlPostPaiement);

            return $responseCurlPostPaiementData->reference;
        } catch (\Throwable $th) {
            echo "Request Not Send";
        }
    }

    public function requestToPayWeb(float $amount, string $phoneNumber, string $operatorName, string $fullname, string $email, string $callback_info, string $custom_id, string $cancel_url="", string $return_url="")
    {
        function curl_post($url, array $post = null, array $options = array())
        {
            $defaults = array(
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_URL => $url,
                CURLOPT_FRESH_CONNECT => 1,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FORBID_REUSE => 1,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_POSTFIELDS => http_build_query($post),
                CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt',
            );

            $ch = curl_init();

            curl_setopt_array($ch, ($options + $defaults));

            if (!$result = curl_exec($ch)) {
                trigger_error(curl_error($ch));
            }

            curl_close($ch);

            return $result;

        }

        $responseIdGet = $this->getIdAndMarchanName();
        $nameMarchandExist = isset($responseIdGet->name);
        if ($nameMarchandExist == true) {

            try {
                $post = array("phoneNumber" => $phoneNumber, "amount" => $amount, "reseau" => $operatorName,
                    "token" => $this->token, "shop" => $this->id, "first_name" => $fullname, "email" => $email,
                    "callback_info" => $callback_info, "reference" => $custom_id, 'return_url' => $return_url,
                    'cancel_url' => $cancel_url);
                $responseCurlPostPaiement = curl_post("https://api.feexpay.me/api/transactions/requesttopay/integration", $post);
                $responseCurlPostPaiementData = json_decode($responseCurlPostPaiement);

                if ($responseCurlPostPaiementData->status == "FAILED") {
                    echo "Paramètres incorrects";
                } else {
                    return array(
                        'payment_url' => $responseCurlPostPaiementData->payment_url,
                        'reference' => $responseCurlPostPaiementData->reference,
                        'order_id' => $responseCurlPostPaiementData->order_id
                    );
                }
            } catch (\Throwable $th) {
                echo "Request Not Send";
            }
        } else {
            return false;
        }
    }

    public function paiementCard(
        float $amount,
        string $phoneNumber,
        string $typeCard,
        string $firstName,
        string $lastName,
        string $email,
        string $country,
        string $address,
        string $district,
        string $currency,
        string $callback_info,
        string $custom_id
    )
    {
        function curl_post($url, array $post = null, array $options = array())
        {
            $defaults = array(

                CURLOPT_POST => 1,

                CURLOPT_HEADER => 0,

                CURLOPT_URL => $url,

                CURLOPT_FRESH_CONNECT => 1,

                CURLOPT_RETURNTRANSFER => 1,

                CURLOPT_FORBID_REUSE => 1,

                CURLOPT_TIMEOUT => 4,

                CURLOPT_POSTFIELDS => http_build_query($post),
                CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt',

            );

            $ch = curl_init();

            curl_setopt_array($ch, ($options + $defaults));

            if (!$result = curl_exec($ch)) {

                trigger_error(curl_error($ch));

            }

            curl_close($ch);

            return $result;
        }

        $responseIdGet = $this->getIdAndMarchanName();
        $nameMarchandExist = isset($responseIdGet->name);
        $systemCardPay = $responseIdGet->systemCardPay;

        //        if ($nameMarchandExist == true) {
            try {
                $post = array(
                    "phone" => $phoneNumber,
                    "amount" => $amount,
                    "reseau" => $typeCard,
                    "token" => $this->token,
                    "shop" => $this->id,
                    "first_name" => $firstName,
                    "last_name" => $lastName,
                    "email" => $email,
                    "country" => $country,
                    "address1" => $address,
                    "district" => $district,
                    "currency" => $currency,
                    "callback_info" => $callback_info,
                    "reference" => $custom_id,
                    "systemCardPay" => $systemCardPay,
                );
                $responseCurlPostPaiement = curl_post("https://api.feexpay.me/api/transactions/card/inittransact/integration", $post);
                $responseCurlPostPaiementData = json_decode($responseCurlPostPaiement);
                
                /* echo $responseCurlPostPaiementData;

                if ($responseCurlPostPaiementData->status == "FAILED") {
                    echo "Une erreur s'est produite";
                } */
                if (isset($responseCurlPostPaiementData->url)) {
                    $result = [
                        'url' => $responseCurlPostPaiementData->url,
                        'reference' => $responseCurlPostPaiementData->reference,
                    ];
                    return $result;
                }
                else {
                    echo "Réponse inattendue de l'API";
                }

            }
            catch (\Throwable $th) {
                echo "Erreur inattendue : " . $th->getMessage();
                echo "Request Not Send";
            }
//        }
//        else {
//            return false;
//        }

    }

    public function getPaiementStatus($paiementRef)
    {
        try {
            $curlGetPaiementWithReference = curl_init("https://api.feexpay.me/api/transactions/getrequesttopay/integration/$paiementRef");
            curl_setopt($curlGetPaiementWithReference, CURLOPT_CAINFO, __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt');
            curl_setopt($curlGetPaiementWithReference, CURLOPT_RETURNTRANSFER, true);
            $responseCurlStatus = curl_exec($curlGetPaiementWithReference);
            $statusData = json_decode($responseCurlStatus);
            curl_close($curlGetPaiementWithReference);

            //if (isset($statusData->status)) {
            $payer = $statusData->payer;
            $responseSendArray = array(
                "amount"=>$statusData->amount,
                "clientNum"=>$payer->partyId,
                "status"=>$statusData->status,
                "reference"=>$statusData->reference
            );
            return $responseSendArray;
           /*  }
            else {
                echo "Réponse inattendue de l'API";
            } */
        }
        catch (\Throwable $th) {
            echo "Get Status Request Not Send";
        }
    }
}
