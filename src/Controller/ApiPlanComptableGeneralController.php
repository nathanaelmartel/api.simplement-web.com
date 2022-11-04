<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiPlanComptableGeneralController extends AbstractController
{
    private $datas = [
        '1' => 'Comptes de capitaux',
        '10' => 'Capital et réserves',
        '11' => 'Report à nouveau',
        '12' => 'Résultat de l\'exercice',
        '13' => 'Subventions d\'investissement',
        '14' => 'Provisions réglementées',
        '15' => 'Provisions pour risques et charges',
        '16' => 'Emprunts et dettes assimilées',
        '17' => 'Dettes rattachées à des participations',
        '18' => 'Comptes de liaison des établissements et sociétés en participation',
        '2' => ' Comptes d\'immobilisations',
        '201' => ' Frais d\'établissement',
        '2011' => ' Frais de constitution',
        '2012' => ' Frais de premier établissement',
        '20121' => ' Frais de prospection',
        '20122' => ' Frais de publicité',
        '2013' => ' Frais d\'augmentation de capital et d\'opérations diverses (fusions, scissions, transformations)',
        '203' => ' Frais de recherche et de développement',
        '205' => ' Concessions et droits similaires, brevets, licences, marques, procédés, logiciels, droits et valeurs similaires',
        '206' => ' Droit au bail',
        '207' => ' Fonds commercial',
        '208' => ' Autres immobilisations incorporelles',
        '21' => ' Immobilisations corporelles',
        '211' => ' Terrains',
        '2111' => ' Terrains nus',
        '2112' => ' Terrains aménagés',
        '2113' => ' Sous-sols et sur-sols',
        '2114' => ' Terrains de gisement',
        '21141' => ' Carrières',
        '2115' => ' Terrains bâtis',
        '21151' => ' Ensembles immobiliers industriels ',
        '21155' => ' Ensembles immobiliers administratifs et commerciaux ',
        '21158' => ' Autres ensembles immobiliers',
        '211581' => ' Affectés aux opérations professionnelles ',
        '211588' => ' Affectés aux opérations non professionnelles ',
        '2116' => ' Compte d\'ordre sur immobilisation (art. 6 du décret n°78-737 du 11 juillet 1978)',
        '212' => ' Agencements et aménagements de terrains (même ventilation que celle du compte 211)',
        '213' => ' Constructions',
        '2131' => ' Bâtiments',
        '21311' => ' Ensembles immobiliers industriels ',
        '21315' => ' Ensembles immobiliers administratifs et commerciaux ',
        '21318' => ' Autres ensembles immobiliers',
        '213181' => ' Affectés aux opérations professionnelles ',
        '213188' => ' Affectés aux opérations non professionnelles ',
        '2135' => ' Installations générales, agencements, aménagements des constructions (même ventilation que celle du compte 2131)',
        '2138' => ' Ouvrages d\'infrastructure',
        '21381' => ' Voies de terre',
        '21382' => ' Voies de fer',
        '21383' => ' Voies d\'eau',
        '21384' => ' Barrages',
        '21385' => ' Pistes d\'aérodromes',
        '214' => ' Constructions sur sol d\'autrui (même ventilation que celle du compte 213)',
        '215' => ' Installations techniques, matériel et outillage industriels',
        '2151' => ' Installations complexes spécialisées',
        '21511' => ' - sur sol propre',
        '21514' => ' - sur sol d\'autrui',
        '2153' => ' Installations à caractère spécifique',
        '21531' => ' - sur sol propre',
        '21534' => ' - sur sol d\'autrui',
        '2154' => ' Matériel industriel',
        '2155' => ' Outillage industriel',
        '2157' => ' Agencements et aménagements du matériel et outillage industriels',
        '218' => ' Autres immobilisations corporelles',
        '2181' => ' Installations générales, agencements, aménagements divers',
        '2182' => ' Matériel de transport',
        '2183' => ' Matériel de bureau et matériel informatique',
        '2184' => ' Mobilier',
        '2185' => ' Cheptel',
        '2186' => ' Emballages récupérables',
        '22' => ' Immobilisations mises en concession',
        '221' => ' Constructions',
        '223' => ' Autres droits réels sur des immeubles',
        '23' => ' Immobilisations en cours',
        '231' => ' Immobilisations corporelles en cours',
        '2312' => ' Terrains',
        '2313' => ' Constructions',
        '2315' => ' Installations techniques, matériel et outillage industriels',
        '2318' => ' Autres immobilisations corporelles',
        '232' => ' Immobilisations incorporelles en cours',
        '237' => ' Avances et acomptes versés sur commandes d\'immobilisations incorporelles',
        '238' => ' Avances et acomptes versés sur commandes d\'immobilisations corporelles',
        '2382' => ' Terrains',
        '2383' => ' Constructions',
        '2385' => ' Installations techniques, matériel et outillage industriels',
        '2388' => ' Autres immobilisations corporelles',
        '25' => ' Parts dans des entreprises liées et créances sur des entreprises liées',
        '26' => ' Participations et créances rattachées à des participations',
        '261' => ' Titres de participation',
        '2611' => ' Actions',
        '2618' => ' Autres titres',
        '262' => ' Titres évalués par équivalence',
        '266' => ' Autres formes de participation',
        '267' => ' Créances rattachées à des participations',
        '2671' => ' Créances rattachées à des participations (groupe)',
        '2674' => ' Créances rattachées à des participations (hors groupe)',
        '2675' => ' Versements représentatifs d\'apports non capitalisés (appel de fonds)',
        '2676' => ' Avances consolidables',
        '2677' => ' Autres créances rattachées à des participations',
        '2678' => ' Intérêts courus',
        '268' => ' Créances rattachées à des sociétés en participation',
        '2681' => ' Principal',
        '2688' => ' Intérêts courus',
        '269' => ' Versements restant à effectuer sur titres de participation non libérés',
        '27' => ' Autres immobilisations financières',
        '271' => ' Titres immobilisés autres que les titres immobilisés de l\'activité portefeuille (droit de propriété)',
        '2711' => ' Actions',
        '2718' => ' Autres titres',
        '272' => ' Titres immobilisés (droits de créance)',
        '2721' => ' Obligations',
        '2722' => ' Bons',
        '273' => ' Titres immobilisés de l\'activité portefeuille (TIAP)',
        '274' => ' Prêts',
        '2741' => ' Prêts participatifs',
        '2742' => ' Prêts aux associés',
        '2743' => ' Prêts au personnel',
        '2748' => ' Autres prêts',
        '275' => ' Dépôts et cautionnements versés',
        '2751' => ' Dépôts',
        '2755' => ' Cautionnements',
        '276' => ' Autres créances immobilisées',
        '2761' => ' Créances diverses',
        '2768' => ' Intérêts courus',
        '27682' => ' Sur titres immobilisés (droits de créance)',
        '27684' => ' Sur prêts',
        '27685' => ' Sur dépôts et cautionnements',
        '27688' => ' Sur créances diverses',
        '277' => ' Actions propres ou parts propres',
        '2771' => ' Actions propres ou parts propres',
        '2772' => ' Actions propres ou parts propres en voie d\'annulation',
        '279' => ' Versements restant à effectuer sur titres immobilisés non libérés',
        '28' => ' Amortissements des immobilisations',
        '280' => ' Amortissements des immobilisations incorporelles',
        '2801' => ' Frais d\'établissement (même ventilation que celle du compte 201)',
        '2803' => ' Frais de recherche et développement',
        '2805' => ' Concessions et droits similaires, brevets, licences, logiciels, droits et valeurs similaires',
        '2807' => ' Fonds commercial',
        '2808' => ' Autres immobilisations incorporelles',
        '281' => ' Amortissements des immobilisations corporelles',
        '2811' => ' Terrains de gisement',
        '2812' => ' Agencements, aménagements de terrains (même ventilation que celle du compte 212)',
        '2813' => ' Constructions (même ventilation que celle du compte 213)',
        '2814' => ' Constructions sur sol d\'autrui (même ventilation que celle du compte 214)',
        '2815' => ' Installations techniques, matériel et outillage industriels (même ventilation que celle du compte 215)',
        '2818' => ' Autres immobilisations corporelles (même ventilation que celle du compte 218)',
        '282' => ' Amortissements des immobilisations mises en concession',
        '29' => ' Dépréciations des immobilisations',
        '290' => ' Dépréciations des immobilisations incorporelles',
        '2905' => ' Marques, procédés, droits, et valeurs similaires',
        '2906' => ' Droit au bail',
        '2907' => ' Fonds commercial',
        '2908' => ' Autres immobilisations incorporelles',
        '291' => ' Dépréciations des immobilisations corporelles (même ventilation que celle du compte 21)',
        '2911' => ' Terrains (autres que terrains de gisement)',
        '292' => ' Dépréciations des immobilisations mises en concession',
        '293' => ' Dépréciations des immobilisations en cours',
        '2931' => ' Immobilisations corporelles en cours',
        '2932' => ' Immobilisations incorporelles en cours',
        '296' => ' Dépréciations des participations et créances rattachées à des participations',
        '2961' => ' Titres de participation',
        '2966' => ' Autres formes de participation',
        '2967' => ' Créances rattachées à des participations (même ventilation que celle du compte 267)',
        '2968' => ' Créances rattachées à des sociétés en participation (même ventilation que celle du compte 268)',
        '297' => ' Dépréciations des autres immobilisations financières',
        '2971' => ' Titres immobilisés autres que les titres immobilisés de l\'activité portefeuille - droit de propriété (même ventilation que celle du compte 271)',
        '2972' => ' Titres immobilisés - droit de créance (même ventilation que celle du compte 272)',
        '2973' => ' Titres immobilisés de l\'activité portefeuille',
        '2974' => ' Prêts (même ventilation que celle du compte 274)',
        '2975' => ' Dépôts et cautionnements versés (même ventilation que celle du compte 275)',
        '2976' => ' Autres créances immobilisées (même ventilation que celle du compte 276)',

    ];

    #[Route('/plan-comptable-general', name: 'api_plan_comptable_general')]
    public function index(): Response
    {
        return $this->json($this->datas);
    }

    #[Route('/plan-comptable-general/{code}/{child}', name: 'api_plan_comptable_general_code')]
    public function search(string $code, $child = null): Response
    {
        $i = 0;
        $c = substr($code, 0, $i++);
        $datas = [];
        while(strlen($c) < strlen($code)) {
            $c = (int)substr($code, 0, $i++);
            if (isset($this->datas[$c])) {
                $datas[] = [
                    'code' => $c,
                    'label' => trim($this->datas[$c])
                ];
            }
        }
        if (!is_null($child)) {
        foreach ($this->datas as $c => $label) {
            if (str_starts_with($c, $code)) {
                $datas[] = [
                    'code' => $c,
                    'label' => trim($label)
                ];
            }
        }
        }

            if (count($datas) > 0) {
            return $this->json($datas);
        }

        return $this->json([
            'code' => $code,
            'label' => 'indéterminée'
        ], 404);
    }
}
