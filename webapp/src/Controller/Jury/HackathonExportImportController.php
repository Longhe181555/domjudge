<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestDisplayData;
use App\Entity\Phase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class HackathonExportImportController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/{contestId<\\d+>}/export-display', name: 'jury_hackathon_export_display', methods: ['GET'])]
    public function exportDisplay(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            return new JsonResponse(['error' => 'Contest not found'], 404);
        }
        $displayData = $this->em->getRepository(ContestDisplayData::class)->findOneBy(['contest' => $contest]);
        $phases = $this->em->getRepository(Phase::class)->findBy(['contest' => $contest], ['phase_order' => 'ASC']);

        $data = [
            'contest' => [
                'id' => $contest->getCid(),
                'name' => $contest->getName(),
                'shortname' => $contest->getShortname(),
                'starttime' => $contest->getStarttimeString(),
                'endtime' => $contest->getEndtimeString(),
            ],
            'displayData' => $displayData ? [
                'title' => $displayData->getTitle(),
                'subtitle' => $displayData->getSubtitle(),
                'bannerUrl' => $displayData->getBannerUrl(),
                'description' => $displayData->getDescription(),
                'metaData' => $displayData->getMetaData(),
                'allowPhase' => $displayData->getAllowPhase(),
            ] : null,
            'phases' => array_map(function(Phase $phase) {
                return [
                    'name' => $phase->getName(),
                    'starttime' => $phase->getStarttime(),
                    'endtime' => $phase->getEndtime(),
                    'description' => $phase->getDescription(),
                    'allow_submit' => $phase->getAllowSubmit(),
                    'allow_manual_judge' => $phase->getAllowManualJudge(),
                    'allow_automatic_judge' => $phase->getAllowAutomaticJudge(),
                    'phase_order' => $phase->getPhaseOrder(),
                    'metadata' => $phase->getMetadata(),
                ];
            }, $phases),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'contest_display_export_' . $contest->getCid() . '.json';
        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route(path: '/{contestId<\d+>}/import-display', name: 'jury_hackathon_import_display', methods: ['POST'])]
    public function importDisplay(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }
        $file = $request->files->get('import_file');
        if (!$file || !$file->isValid()) {
            $this->addFlash('danger', 'No file uploaded or upload error.');
            return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
        }
        $json = file_get_contents($file->getPathname());
        $data = json_decode($json, true);
        if (!$data) {
            $this->addFlash('danger', 'Invalid JSON file.');
            return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
        }
        // Import display data
        if (!empty($data['displayData'])) {
            $repo = $this->em->getRepository(ContestDisplayData::class);
            $displayData = $repo->findOneBy(['contest' => $contest]) ?? new ContestDisplayData();
            $displayData->setContest($contest);
            $displayData->setTitle($data['displayData']['title'] ?? '');
            $displayData->setSubtitle($data['displayData']['subtitle'] ?? '');
            $displayData->setBannerUrl($data['displayData']['bannerUrl'] ?? null);
            $displayData->setDescription($data['displayData']['description'] ?? '');
            $displayData->setMetaData($data['displayData']['metaData'] ?? []);
            if (array_key_exists('allowPhase', $data['displayData'])) {
                $displayData->setAllowPhase((bool)$data['displayData']['allowPhase']);
            }
            $this->em->persist($displayData);
        }
        // Remove old phases
        $phases = $this->em->getRepository(Phase::class)->findBy(['contest' => $contest]);
        foreach ($phases as $phase) {
            $this->em->remove($phase);
        }
        // Import phases
        if (!empty($data['phases'])) {
            foreach ($data['phases'] as $pdata) {
                $phase = new Phase();
                $phase->setContest($contest);
                $phase->setName($pdata['name'] ?? '');
                $phase->setStarttime($pdata['starttime'] ?? null);
                $phase->setEndtime($pdata['endtime'] ?? null);
                $phase->setDescription($pdata['description'] ?? null);
                $phase->setAllowSubmit($pdata['allow_submit'] ?? false);
                $phase->setAllowManualJudge($pdata['allow_manual_judge'] ?? false);
                $phase->setAllowAutomaticJudge($pdata['allow_automatic_judge'] ?? false);
                $phase->setPhaseOrder($pdata['phase_order'] ?? 1);
                $phase->setMetadata($pdata['metadata'] ?? null);
                $this->em->persist($phase);
            }
        }
        $this->em->flush();
        $this->addFlash('success', 'Import successful.');
        return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
    }
}
