<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestDisplayData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class PhaseController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/{contestId<\d+>}/enable-phases', name: 'jury_hackathon_enable_phases', methods: ['POST'])]
    public function enablePhases(int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }
        $displayData = $this->em->getRepository(ContestDisplayData::class)->findOneBy(['contest' => $contest]);
        if (!$displayData) {
            $displayData = new ContestDisplayData();
            $displayData->setContest($contest);
        }
        if (!$displayData->getAllowPhase()) {
            $displayData->setAllowPhase(true);
            $this->em->persist($displayData);
            $this->em->flush();
            $this->addFlash('success', 'Phases have been enabled for this contest.');
        } else {
            $this->addFlash('info', 'Phases are already enabled.');
        }
        return $this->redirectToRoute('jury_hackathon_phases', ['contestId' => $contestId]);
    }

    #[Route(path: '/{contestId<\\d+>}/phases', name: 'jury_hackathon_phases')]
    public function phasesTab(int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $displayData = $this->em->getRepository(ContestDisplayData::class)->findOneBy(['contest' => $contest]);
        $phases = $this->em->getRepository(\App\Entity\Phase::class)->findBy(
            ['contest' => $contest],
            ['phase_order' => 'ASC']
        );
        // If allowPhase is off, only show the first phase
        if ($displayData && !$displayData->getAllowPhase() && count($phases) > 0) {
            $phases = [$phases[0]];
        }
        return $this->render('extensions_plugin/hackathon_phases.html.twig', [
            'contest' => $contest,
            'phases' => $phases,
            'displayData' => $displayData,
        ]);
    }
}
