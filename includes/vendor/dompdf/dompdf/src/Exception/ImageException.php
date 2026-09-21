<?php
 namespace Dompdf\Exception; use Dompdf\Exception; class ImageException extends Exception { function __construct($message = null, $code = 0) { parent::__construct($message, $code); } } 