<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception as Exception;
use SendPulse\SendpulseApi as SendPulseApi;

class SendPulse extends Base
{
    protected $isTableMode = 0;

    /* @var $sendPulseApi SendPulseApi */
    private $sendPulseApi;

    /* @return SendpulseApi */
    public function getInstanceSendPulseApi()
    {
        if (!$this->sendPulseApi) {
            $t = new DB("service", "s");
            $t->select('sp.code, sp.value');
            $t->innerJoin('service_parameter sp', 'sp.id_service = s.id');
            $t->where('s.code = "?"', "sendpulse");
            $result = $t->getList();
            $secret = [];
            foreach ($result as $value)
                $secret[$value['code']] = $value['value'];
            $this->sendPulseApi = new SendpulseApi($secret["id"], $secret["secret"], "session");
        }
        return $this->sendPulseApi;
    }

    public function getBookById($id)
    {
        return $this->getInstanceSendPulseApi()->getBookInfo($id);
    }

    public function createAddressBook($bookName)
    {
        return $this->getInstanceSendPulseApi()->createAddressBook($bookName)->id;
    }


    public function editAddressBook($id, $name)
    {
        return $this->getInstanceSendPulseApi()->editAddressBook($id, $name)->id;
    }

    public function removeAddressBook($idBook)
    {
        $this->getInstanceSendPulseApi()->removeAddressBook($idBook);
    }

    public function addEmails($idsBooks = [], $emails = [])
    {
        foreach ($idsBooks as $idBook)
            $this->getInstanceSendPulseApi()->addEmails($idBook, $emails);
    }

    public function removeEmails($idsBooks = [], $emails = [])
    {
        foreach ($idsBooks as $idBook)
            $this->getInstanceSendPulseApi()->removeEmails($idBook, $emails);
    }

    public function removeEmailFromAllBooks($email)
    {
        $this->getInstanceSendPulseApi()->removeEmailFromAllBooks($email);
    }

    public function deleteCampaign($idCampaign)
    {
        $this->getInstanceSendPulseApi()->cancelCampaign($idCampaign);
    }

    public function createCampaign($senderName, $subject, $body, $idBook, $sendDate, $name = null)
    {
        $senders = $this->getInstanceSendPulseApi()->listSenders();
        $senderEmail = null;
        foreach ($senders as $sender) {
            $senderEmail = $sender->email;
            break;
        }
        return $this->getInstanceSendPulseApi()->createCampaign($senderName, $senderEmail,
            $subject, $body, $idBook, $sendDate, $name);
    }

}