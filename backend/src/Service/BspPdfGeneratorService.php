<?php

namespace App\Service;

use App\Entity\Bsp;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class BspPdfGeneratorService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generate(Bsp $bsp): string
    {
        $assetExit = $bsp->getAssetExit();

        $html = $this->twig->render('bsp/bon_sortie_provisoire.html.twig', [
            'bsp' => $bsp,
            'service' => $bsp->getService(),
            'asset' => $assetExit?->getAsset(),
            'dateEtablissement' => $bsp->getDateEtablissement()?->format('d/m/Y') ?? '......................',
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
