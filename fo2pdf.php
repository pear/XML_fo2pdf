<?php
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Christian Stocker <chregu@nomad.ch>                         |
// +----------------------------------------------------------------------+
//
// $Id$


/**
* fo to pdf converter.
*
* with fo (formating objects) it's quite easy to convert xml-documents into
*  pdf-docs (and not only pdf, but also ps, pcl, txt and more)
*
* An introduction into formating objects can be found at
*  http://www.w3.org/TR/xsl/slice6.html#fo-section
*  http://www.ibiblio.org/xml/books/bible/updates/15.html
* A tutorial is here:
*  http://www.xml.com/pub/a/2001/01/17/xsl-fo/
*  http://www.xml.com/pub/a/2001/01/24/xsl-fo/
* A html_to_fo.xsl can also be found there
*  http://www.xml.com/2001/01/24/xsl-fo/fop_article.tgz
*  but it didn't work for my simple xhtml files..
*
* The way to use this class is, produce a fo-file from a xml-file with a
* xsl-stylesheet, then feed this class with this fo-file and you get a pdf
* back (either directly to the browser for really dynamic-pdf production or
* as a file on your filesystem)
*
* It is recommended to use the Cache-Classes from PEAR, if you want dynamic
* pdf production, since the process of making the pdfs takes some time. For
* an example of how to  use Cache and fo2pdf see below.
*
* Requirements:
*
*  You need Fop (version 0.20.1 was used for this) from the xml-apache 
*   project (http://xml.apache.org/fop) and Java (1.1.x or later, i tested 
*   it with 1.2.2 from sun on linux, see the Fop-Docs for details).
*  Furthermore you have to compile your php with --with-java and to adjust
*   your php.ini file. It can be a rather painful task to get java and php
*   to work together. (i also tested this only with jdk 1.2.2, i couldn't 1.3.1 
*   get to work)
*   See http://www.phpbuilder.com/columns/marknold20001221.php3 or
*   http://www.linuxwebdevnews.com/articles/php-java-xslt.php?pid=347
*   for more details about java and php or ask me, if you're stuck
*   (especially with linux. windows is not my area..)
*
* Usage:
*    require_once("XML/fo2pdf.php");
*    //make a pdf from simple.fo and save the pdf in a tmp-folder
*    $fop = new xml_fo2pdf();
*    // the following 2 lines are the default settins, so not
*    // necessary here, but you can set it to other values        
*    $fop->setRenderer("pdf");
*    $fop->setContentType("application/pdf");
*     if (PEAR::isError($error = $fop->run("simple.fo")))
*     {
*      die("FOP ERROR: ". $error->getMessage());
*     }
*    //print pdf to the outputbuffer,
*    // including correct Header ("Content-type: application/pdf")
*    $fop->printPDF();
*    //delete the temporary pdf file
*    $fop->deletePDF();
*
*   With Cache:
*    require_once("XML/fo2pdf.php");
*    require_once("Cache/Output.php");
*    $container = "file";
*    $options = array("cache_dir"=>"/tmp/");
*    $cache = new Cache_Output("$container",$options);
*    $cache_handle = $cache->generateID($REQUEST_URI);
*    if ($content = $cache->start($cache_handle)) {
*      Header("Content-type: application/pdf");
*      print $content;
*      die();
*    }
*    $fop = new xml_fo2pdf();
*    $fop->run("simple.fo");
*    $fop->printPDF();
*    $fop->deletePDF();
*    print $cache->end("+30");
*
* @author   Christian Stocker <chregu@nomad.ch>
* @version  $Id$
* @package  XML
*/
require_once( "PEAR.php") ;

class XML_fo2pdf extends pear {

    /**
    * fo-file used in this class
    *
    * @var  string
    */
    var $fo = "";

    /**
    * pdf-file used in this class
    *
    * @var  string
    */
    var $pdf = "";

    /**
    * Where the temporary fo and pdf files should be stored
    *
    * @var  string
    */
    var $tmpdir = "/tmp";

    /**
    * A prefix for the temporary files
    *
    * @var  string
    */
    var $tmppdfprefix = "pdffo";

    /**
    * the render Type. At the moment (fop 0.20.1), possible values are
    * - awt  
    * - mif
    * - pcl
    * - pdf
    * - ps  
    * - txt 
    * - xml
    *
    * @var string
    */
    var $renderer = "pdf";
    
    /**
    * the content-type to be sent if printPDF is called.
    *
    * @var contenttype
    " @see printPDF()
    */
    var $contenttype = "application/pdf";
    
    /**
    * constructor
    * ATTENTION (you've been warned!):
    * You should not pass the values here, 'cause then you don't have
    *  Error-Reporting. This variables are only here for Backwards Compatibilty..
    *  Use $fop->run("input.fo","output.pdf") instead.
    *
    * @param    string  $fo     file input fo-file (do not use it anymore)
    * @param    string  $pdf    file output pdf-file (do not use it anymore)
    * @see run(), runFromString(), runFromFile()
    * @access public
    */
    function xml_fo2pdf ($fo = Null, $pdf = "")
    {
        if (!(is_null($fo))) {
           $this->run($fo, $pdf);
        }
    }

    /**
    * Calls the Main Fop-Java-Programm
    *
    * One has to pass an input fo-file
    *  and if the pdf should be stored permanently, a filename/path for
    *  the pdf.
    *  if the pdf is not passed or empty/false, a temporary pdf-file
    *   will be created
    *
    * @param    string  $fo     file input fo-file
    * @param    string  $pdf    file output pdf-file
    * @param    boolean $DelFo  if the fo should be deleted after execution
    * @see runFromString()
    */
    function run($fo, $pdf = "", $DelFo = False)
    {
        if (!$pdf)
            $pdf = tempnam($this->tmpdir, $this->tmppdfprefix);

        $this->pdf = $pdf;
        $this->fo = $fo;
        $options = array( $this->fo,"-".$this->renderer,$this->pdf);

        /**
        * according to the documentation, the following  lines should be enough, 
        * to do, what we want. but it didn't work. Yes, it did, but it produced
        * approx. 10 pdf-files for each run...
        *
        * $options = new Java("org.apache.fop.apps.CommandLineOptions",$options);
        * $starter = $options->getStarter();
        * $starter->run();
        *
        * Therefore i took the code from org/apache/fop/apps/CommandLineStarter.java
        *  converted it to php-code and it works now... if anyone has a better solution
        *  please inform me ;)
        */
        java_last_exception_clear();
        $commandLineOptions= @new Java("org.apache.fop.apps.CommandLineOptions",$options);

        if ($exc = java_last_exception_get()) 
        {            
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }


        $starter = new Java("org.apache.fop.apps.CommandLineStarter",$commandLineOptions);

        if ($exc = java_last_exception_get()) 
        {
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }

        
        $input = $commandLineOptions->getInputHandler();

        $parser = $input->getParser();
        $starter->setParserFeatures($parser);

        $driver = new Java ("org.apache.fop.apps.Driver");

        $driver->setBufferFile($commandLineOptions->getBufferFile());
        
        $renderer = @$commandLineOptions->getRenderer();
        if ($exc = java_last_exception_get()) 
        {          
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }
        
        @$driver->setRenderer($renderer);
        if ($exc = java_last_exception_get()) 
        {          
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }

        $stream = @new Java("java.io.FileOutputStream",$commandLineOptions->getOutputFile());
        if ($exc = java_last_exception_get()) 
        {
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }

        
        @$driver->setOutputStream($stream);
        if ($exc = java_last_exception_get()) 
        {
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }
        
        $renderer = $driver->getRenderer();
        $renderer->setOptions($commandLineOptions->getRendererOptions());

        @$driver->render($parser, $input->getInputSource());
        if ($exc = java_last_exception_get()) 
        {
             return new PEAR_Error($exc->getMessage(), 11, PEAR_ERROR_RETURN, null, null );
        }
        
        if ($DelFo) {
            $this->deleteFo($fo);
        }
        
        return True;
    }

    /**
    * If the fo is a string, not a file, use this.
    *
    * If you generate the fo dynamically (for example with a
    *  xsl-stylesheet), you can use this method
    *
    * The Fop-Java program needs a file as an input, so a
    *  temporary fo-file is created here (and will be deleted
    *  in the run() function.)
    *
    * @param    string  $fostring   fo input fo-string
    * @param    string  $pdf        file output pdf-file
    * @see run()
    */
    function runFromString($fostring, $pdf = "")
    {
        $fo = tempnam($this->tmpdir, $this->tmppdfprefix);
        $fp = fopen($fo, "w+");
        fwrite($fp, $fostring);
        fclose($fp);
        return $this->run($fo, $pdf, True);
    }
    /**
    * A wrapper to run for better readabilty
    *
    * This method just calls run....
    *
    * @param    string  $fo     fo input fo-string
    * @param    string  $pdf    file output pdf-file
    * @see run()
    */
    function runFromFile($fo, $pdf = "")
    {
        return $this->run($fo, $pdf);
    }

    /**
    * Deletes the created pdf
    *
    * If you dynamically create pdfs and you store them
    *  for example in a Cache, you don't need it afterwards.
    * If no pdf is given, the one generated in run() is deleted
    *
    * @param    string  $pdf    file output pdf-file
    * @access public
    */
    function deletePDF($pdf = "")
    {
        if (!$pdf)
            $pdf = $this->pdf;
        unlink ($pdf);
    }

    /**
    * Deletes the created fo
    *
    * If you dynamically create fos, you don't need it afterwards.
    * If no fo-file is given, the one generated in run() is deleted
    *
    * @param    string  $fo  file input fo-file
    */
    function deleteFo($fo = "")
    {
        if (!$fo)
            $fo = $this->fo;

        unlink ($fo);
    }

    /**
    * Prints the content header and the generated pdf to the output
    *
    * If you want to dynamically generate pdfs and return them directly
    *  to the browser, use this.
    * If no pdf-file is given, the generated from run() is taken.
    *
    * @param    string  $pdf    file output pdf-file
    * @see returnPDF()
    * @access public    
    */
    function  printPDF($pdf = "")
    {
        $pdf = $this->returnPDF($pdf);
        Header("Content-type: ".$this->contenttype."\nContent-Length: " . strlen($pdf));
        print $pdf;
    }

    /**
    * Returns the pdf
    *
    * If no pdf-file is given, the generated from run() is taken.
    *
    * @param    string  $pdf    file output pdf-file
    * @return   string pdf
    * @see run()    
    */
    function returnPDF($pdf = "")
        {
       if (!$pdf)
           $pdf = $this->pdf;

       $fd = fopen($pdf, "r");
       $content = fread( $fd, filesize($pdf) );
       fclose($fd);
       return $content;
    }
    
    /**
    * sets the rendertype
    *
    * @see $this-renderer
    * @access public
    */  
    
    function setRenderer($renderer = "pdf")
    {
        $this->renderer = $renderer;
    }

    /**
    * sets the content-type
    *
    * @see $this-contenttype
    * @access public

    */  
    
    function setContentType($contenttype = "application/pdf")
    {
        $this->contenttype = $contenttype;
    }
    
}
?>
