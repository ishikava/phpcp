<?php

/**
 * This is a class that provides basic functionality of CryptoPro PHP plugin
 *
 * @copyright Copyright (c) Maksim Kiselev <magnumru@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 * @link https://github.com/ishikava/phpcp GitHub
 */

namespace Ishikava\Phpcp;

use Ramsey\Uuid\Uuid;
use ZipArchive;

class CryptoPro
{
    /**
     * Setup a CryptoPro TSA address
     */
    const TSA_ADDRESS = 'http://testca.cryptopro.ru/tsp/tsp.srf';

    /**
     * Defines a type of CryptoPro signature
     */
    const SIGNATURE_TYPE = 2;

    /**
     * Defines a CryptoPro signature method
     */
    const SIGNATURE_METHOD = 'urn:ietf:params:xml:ns:cpxmlsec:algorithms:gostr34102012-gostr34112012-256';

    /**
     * Defines a CryptoPro digest method
     */
    const DIGEST_METHOD = 'urn:ietf:params:xml:ns:cpxmlsec:algorithms:gostr34112012-256';

    /**
     * Stores a system certificate
     */
    private $certificate;

    /**
     * Set Up correct certificate
     *
     * @param string $cert_sha1_hash a string that uniquely identifies a certificate
     *
     * @param int $valid_only to use-whether only valid certificates
     *
     * @param int $number serial number of the certificate in the system
     */
    public function __construct($cert_sha1_hash, $valid_only = 0, $number = 1)
    {
        if (!$this->certificate) {
            $this->certificate = $this->SetupCertificate(CURRENT_USER_STORE,
                "My", STORE_OPEN_READ_ONLY, CERTIFICATE_FIND_SHA1_HASH,
                $cert_sha1_hash, $valid_only, $number);
        }
    }

    /**
     * Signs a prepared XML file
     *
     * @param $content .must be a content of valid XML file .ex: $cp->signXML(file_get_contents($_FILES['file']['tmp_name']))
     * @return mixed
     */
    public function signXML($content)
    {
        $signer = new \CPSigner();
        $signer->set_TSAAddress(self::TSA_ADDRESS);
        $signer->set_Certificate($this->certificate);

        $sd = new \CPSignedXML();
        $sd->set_SignatureType(self::SIGNATURE_TYPE);
        $sd->set_SignatureMethod(self::SIGNATURE_METHOD);
        $sd->set_DigestMethod(self::DIGEST_METHOD);
        $sd->set_Content($content);

        try {
            return $sd->Sign($signer, "");
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Signs any provided file with detached signature
     *
     * @param $content .must be a file
     * @return mixed
     */
    public function signFile($content)
    {
        $signer = new \CPSigner();
        $signer->set_TSAAddress(self::TSA_ADDRESS);
        $signer->set_Certificate($this->certificate);

        $sd = new \CPSignedData();
        $sd->set_Content($content);

        try {
            return $sd->Sign($signer, 0, STRING_TO_UCS2LE);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Signs any SMEV-3 formatted XML file
     *
     * @param $content .must be a content of valid XML file
     * @return mixed
     */
    public function signCades($content)
    {
        $signer = new \CPSigner();
        $signer->set_TSAAddress(self::TSA_ADDRESS);
        $signer->set_Certificate($this->certificate);

        $sd = new \CPSignedData();
        $sd->set_Content($content);

        try {
            return $sd->SignCades($signer, 0x01, 1, 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Simple shell script
     *
     * @param $file .a file to sign
     *
     * @param $cert_sha1_hash a string that uniquely identifies a certificate
     *
     * @return null
     */
    public function signExec($file, $cert_sha1_hash)
    {
        exec('cd ../tmp;    /opt/cprocsp/bin/amd64/cryptcp -sign -thumbprint ' . $cert_sha1_hash . ' ' . $file, $out, $err);
        if ($err !== 0) {
            echo 'CryptoPro Не удалось подписать файл';
        }
        return null;
    }

    /**
     * Try to sign a file with detached signature and returns ZIP archive with file itself and its signature
     *
     * @param $file any file
     * @param string $type a type of output document ex. "pdf" or "html"
     * @return string base64 encoded ZIP archive
     * @throws \Exception
     */
    public function getSignedZipBase64($file, $type)
    {
        $name = Uuid::uuid1()->toString();

        if ($type == 'pdf') {
            file_put_contents('../tmp/' . $name . '.' . $type,
                base64_decode($file));
        } else {
            file_put_contents('../tmp/' . $name . '.' . $type, $file);
        }

        $this->signExec('../tmp/' . $name . '.' . $type);

        $zip = new ZipArchive();
        $zipName = '../tmp/' . $name . '.zip';

        $zip->open($zipName, ZipArchive::CREATE);
        $zip->addFile('../tmp/' . $name . '.' . $type, 'File.' . $type);
        $zip->addFile('../tmp/' . $name . '.' . $type . '.sig',
            'File.' . $type . '.sig');
        $zip->close();

        $zipData = file_get_contents($zipName);
        $signedZip = base64_encode($zipData);

        if (file_exists($zipName)) {
            unlink($zipName);
        }
        if (file_exists('../tmp/' . $name . '.' . $type)) {
            unlink('../tmp/' . $name . '.' . $type);
        }
        if (file_exists('../tmp/' . $name . '.' . $type . '.sig')) {
            unlink('../tmp/' . $name . '.' . $type . '.sig');
        }

        return $signedZip;
    }

    /**
     * Verifies a digital signature of XML file
     *
     * @param $content
     * @return bool
     */
    public function verify($content)
    {
        try {
            $verify = new \CPSignedXML();
            $verify->Verify($content, "");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get a system certificate
     *
     * @return mixed
     */
    public function getCertificate()
    {
        return $this->certificate->Export(0);
    }

    /**
     * Setup CryptoPro certificate store
     *
     * @param $location
     * @param $name
     * @param $mode
     * @return \CPStore
     */
    private function SetupStore($location, $name, $mode)
    {
        $store = new \CPStore();
        $store->Open($location, $name, $mode);
        return $store;
    }

    /**
     * Setup Certificates
     *
     * @param $location
     * @param $name
     * @param $mode
     * @return mixed
     */
    private function SetupCertificates($location, $name, $mode)
    {
        $store = $this->SetupStore($location, $name, $mode);
        $certs = $store->get_Certificates();
        return $certs;
    }

    /**
     * Setup Certificate
     *
     * @param $location
     * @param $name
     * @param $mode
     * @param $find_type
     * @param $query
     * @param $valid_only
     * @param $number
     * @return mixed
     */
    private function SetupCertificate(
        $location,
        $name,
        $mode,
        $find_type,
        $query,
        $valid_only,
        $number
    )
    {
        $certs = $this->SetupCertificates($location, $name, $mode);
        $certs = $certs->Find($find_type, $query, $valid_only);
        return $certs->Item($number);
    }

}