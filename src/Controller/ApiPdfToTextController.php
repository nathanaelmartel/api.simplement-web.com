<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class ApiPdfToTextController extends AbstractController
{
    #[Route('/api/pdf-to-text', name: 'app_api_pdf_to_text')]
    public function index(Request $request): Response
    {
        $filename = time().'.pdf';
        file_put_contents($filename, $request->request->get('file_content'));

        $cli = sprintf(
            'pdftotext "%s" "%s.txt" -layout -fixed %s',
            $filename,
            $filename,
            $request->request->get('fixed', 4)
        );

        $process = Process::fromShellCommandline($cli);
        $process->mustRun();
        $lines = explode("\n", (string)file_get_contents($filename.'.txt'));

        unlink($filename.'.txt');
        unlink($filename);

        return $this->json($lines);
    }
}
