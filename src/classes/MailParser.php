<?php

/**
 * MailParser Class
 * 
 * Handles parsing of email messages and extraction of their components
 */
class MailParser
{
    private $rawMessage;
    private $parsedData;
    
    public function __construct($rawMessage = null)
    {
        $this->rawMessage = $rawMessage;
        $this->parsedData = [];
    }
    
    /**
     * Parse a raw email message
     */
    public function parse($rawMessage = null)
    {
        if ($rawMessage !== null) {
            $this->rawMessage = $rawMessage;
        }
        
        if (empty($this->rawMessage)) {
            throw new Exception('No message to parse');
        }
        
        $this->parsedData = [
            'headers' => [],
            'body_text' => '',
            'body_html' => '',
            'attachments' => [],
            'message_id' => '',
            'in_reply_to' => '',
            'from' => [],
            'to' => [],
            'subject' => '',
            'date' => ''
        ];
        
        $this->parseHeaders();
        $this->parseBody();
        
        return $this->parsedData;
    }
    
    /**
     * Parse email headers
     */
    private function parseHeaders()
    {
        $lines = explode("\n", $this->rawMessage);
        $headers = [];
        
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            
            if (empty($line)) {
                break;
            }
            
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $headerName = strtolower(trim($matches[1]));
                $headers[$headerName] = trim($matches[2]);
            }
        }
        
        $this->parsedData['headers'] = $headers;
        $this->extractHeaderData($headers);
    }
    
    /**
     * Extract specific header data
     */
    private function extractHeaderData($headers)
    {
        $this->parsedData['subject'] = $headers['subject'] ?? '';
        $this->parsedData['date'] = $headers['date'] ?? '';
        $this->parsedData['message_id'] = $headers['message-id'] ?? '';
        $this->parsedData['in_reply_to'] = $headers['in-reply-to'] ?? '';
        
        $this->parsedData['from'] = $this->parseAddresses($headers['from'] ?? '');
        $this->parsedData['to'] = $this->parseAddresses($headers['to'] ?? '');
    }
    
    /**
     * Parse email addresses
     */
    private function parseAddresses($addressString)
    {
        $addresses = [];
        
        if (empty($addressString)) {
            return $addresses;
        }
        
        $parts = explode(',', $addressString);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (preg_match('/^"?([^"<]*)"?\s*<?([^>@\s]+@[^>\s]+)>?$/', $part, $matches)) {
                $name = trim($matches[1]);
                $email = trim($matches[2]);
                
                $addresses[] = [
                    'name' => $name,
                    'email' => $email
                ];
            } elseif (preg_match('/^([^@\s]+@[^\s]+)$/', $part, $matches)) {
                $addresses[] = [
                    'name' => '',
                    'email' => trim($matches[1])
                ];
            }
        }
        
        return $addresses;
    }
    
    /**
     * Parse email body
     */
    private function parseBody()
    {
        $lines = explode("\n", $this->rawMessage);
        $inHeaders = true;
        $bodyLines = [];
        
        foreach ($lines as $line) {
            if ($inHeaders) {
                if (empty(trim($line))) {
                    $inHeaders = false;
                    continue;
                }
            } else {
                $bodyLines[] = $line;
            }
        }
        
        $body = implode("\n", $bodyLines);
        $this->parsedData['body_text'] = $body;
    }
    
    /**
     * Get parsed data
     */
    public function getParsedData()
    {
        return $this->parsedData;
    }
    
    /**
     * Get sender information
     */
    public function getSender()
    {
        return $this->parsedData['from'][0] ?? null;
    }
    
    /**
     * Get recipients
     */
    public function getRecipients()
    {
        return $this->parsedData['to'];
    }
}
