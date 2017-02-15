<?php

namespace SE\Shop;

class Email
{
    private $subject;
    private $toEmail;
    private $body;
    private $headers;

    private $messages = [];
    private $attaches = [];

    private $boundary;

    public function __construct($subject, $toEmail, $fromEmail, $msg, $contentType = null, $filename = null,
                                $mimeType = "application/octet-stream", $mimeFilename = false)
    {
        $subject = stripslashes($subject);
        $this->boundary = '==================' . strtoupper(uniqid()) . '==';
        //smtp_headers
        $this->subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $this->toEmail = $toEmail;

        //headers
        $this->headers = "From: " . $fromEmail . "\n";
        $this->headers .= "Reply-To: " . $fromEmail . "\n";
        $this->headers .= "X-Mailer: PHP5\n";
        $this->headers .= "X-Sender: " . $fromEmail . "\n";
        $this->headers .= "Mime-Version: 1.0\n";
        $this->headers .= "Content-Type: multipart/mixed; ";
        $this->headers .= "boundary=\"" . $this->boundary . "\"\n\n";


        if (!$contentType)
            $contentType = 'text/html';

        $this->addText($msg, $contentType);

        if (!empty($filename)) {
            $sileList = explode(';', $filename);
            foreach ($sileList as $file) {
                $file = trim($file);
                if (empty($file)) continue;
                $this->attach($file, '', $mimeType);
            }
        }
    }

    public function attach($filename = null, $filePath = null, $mime = null)
    {
        if (empty($mime))
            $mime = 'application/octet-stream';
        $content_id = uniqid();
        $this->attaches[] = ['filename' => $filename, 'filepath' => $filePath,
            'mime' => $mime, 'cid' => $content_id];
        return $content_id;
    }
    public function addText($message, $mime)
    {
        if (empty($mime))
            $mime = 'text/plain';
        $this->messages[] = array('message' => $message, 'mime' => $mime);
    }

    private function encodeFile($sourceFile)
    {
        $encoded = null;
        if (is_readable($sourceFile))
        {
            $fd = fopen($sourceFile, "r");
            $contents = fread($fd, filesize($sourceFile));
            $encoded = $this->chunkSplit(base64_encode($contents));
            fclose($fd);
        }
        return $encoded;
    }

    private function chunkSplit($str)
    {
        $tmp = $str;
        $len = strlen($tmp);
        $out = "";
        while ($len > 0)
        {
            if ($len >= 76)
            {
                $out = $out . substr($tmp, 0, 76) . "\r\n";
                $tmp = substr($tmp, 76);
                $len = $len - 76;
            } else
            {
                $out = $out . $tmp . "\r\n";
                $tmp = "";
                $len = 0;
            }
        }
        return $out;
    }

    private function create()
    {
        foreach ($this->messages as $message)
        {
            $this->body .= '--' . $this->boundary . "\n";
            $this->body .= "Content-Type: " . $message['mime'] . "; charset=\"UTF-8\"\n";
            $this->body .= "Content-Transfer-Encoding: 8bit\n";
            $this->body .= "\n" . $message['message'] . "\n\n";
        }
        foreach ($this->attaches as $attach)
        {
            if (substr($attach['filename'], 0, 1) == '/'){
                $ffr = true;
            } else {
                $ffr = false;
            }

            $this->body .= '--' . $this->boundary . "\n";
            if (!$ffr){
                $this->body .= "Content-Type: " . $attach['mime'] . "; name=\"" . $attach['filename'] . "\"\n";
            } else {
                $this->body .= "Content-Type: " . $attach['mime'] . "; name=\"" . end(explode('/',$attach['filename'])) . "\"\n";
            }
            if (!$ffr){
                $this->body .= "Content-disposition: attachment; name=\"" . $attach['filename'] . "\"\n";
            } else {
                $this->body .= "Content-disposition: attachment; name=\"" . end(explode('/',$attach['filename'])) . "\"\n";
            }
            if ($attach['cid'])
                $this->body .= "Content-ID: <" . $attach['cid'] . ">\n";
            $this->body .= "Content-Transfer-Encoding: base64\n";
            if (!empty($attach['filepath']) && substr($attach['filepath'], 0, 1) !== '/')
                $attach['filepath'] = '/' . $attach['filepath'];
            if (!empty($attach['filepath']) && substr($attach['filepath'], -1, 1) == '/')
                $attach['filepath'] = substr($attach['filepath'], 0, -1);
            if (!$ffr){
                $this->body .= "\n" . $this->encodeFile($_SERVER['DOCUMENT_ROOT'] . '/files' . $attach['filepath'] . '/' . $attach['filename']) . "\n";
            } else {
                $this->body .= "\n" . $this->encodeFile($attach['filename']) . "\n";
            }
        }
        $this->body .= '--' . $this->boundary . "--";
    }

    public function showHeaders()
    {
        echo '<pre>';
        echo $this->headers;
        echo $this->body;
        echo '</pre>';
    }

    public function send()
    {
        $this->create();
        return mail($this->toEmail, $this->subject, $this->body, $this->headers);
    }
}