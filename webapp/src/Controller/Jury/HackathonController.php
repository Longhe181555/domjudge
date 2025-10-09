<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Service\DOMJudgeService;
use App\Entity\Contest;
use App\Entity\ContestDisplayData;
use App\Form\Type\ContestDisplayDataType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class HackathonController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '', name: 'jury_hackathon')]
    public function index(): Response
    {
        $em = $this->em;
        $contests = $em->createQueryBuilder()
            ->select('c')
            ->from(Contest::class, 'c')
            ->orderBy('c.starttime', 'DESC')
            ->groupBy('c.cid')
            ->getQuery()->getResult();

        // Table fields (copy from ContestController)
        $table_fields = [
            'cid'          => ['title' => 'CID', 'sort' => true],
            'shortname'    => ['title' => 'shortname', 'sort' => true],
            'name'         => ['title' => 'name', 'sort' => true],
            'activatetime' => ['title' => 'activate', 'sort' => true],
            'starttime'    => ['title' => 'start', 'sort' => true, 'default_sort' => true, 'default_sort_order' => 'desc'],
            'endtime'      => ['title' => 'end', 'sort' => true],
        ];

        $contests_table = [];
        foreach ($contests as $contest) {
            $contestdata = [];
            foreach ($table_fields as $k => $v) {
                if (method_exists($contest, 'get' . ucfirst($k))) {
                    $contestdata[$k] = ['value' => $contest->{'get' . ucfirst($k)}()];
                } elseif (property_exists($contest, $k)) {
                    $contestdata[$k] = ['value' => $contest->$k];
                } else {
                    $contestdata[$k] = ['value' => null];
                }
            }
            $contests_table[] = [
                'data' => $contestdata,
                'actions' => [
                    [
                        'icon' => 'edit',
                        'title' => 'Configure display data',
                        'link' => $this->generateUrl('jury_hackathon_display', ['contestId' => $contest->getCid()]),
                    ],
                ],
                'link' => null,
                'cssclass' => '',
            ];
        }

        return $this->render('extensions_plugin/hackathon.html.twig', [
            'contests_table' => $contests_table,
            'table_fields' => $table_fields,
        ]);
    }

    #[Route(path: '/{contestId<\\d+>}/display', name: 'jury_hackathon_display')]
    public function displayConfig(Request $request, int $contestId): Response
    {
        try {
            $contest = $this->em->getRepository(Contest::class)->find($contestId);
            if (!$contest) {
                $this->addFlash('danger', 'Contest not found.');
                return $this->redirectToRoute('jury_hackathon');
            }


            $repo = $this->em->getRepository(ContestDisplayData::class);
            $displayData = $repo->findOneBy(['contest' => $contest]);
            if (!$displayData) {
                $displayData = new ContestDisplayData();
                $displayData->setContest($contest);
                $this->em->persist($displayData);
                $this->em->flush();
            }

            // Ensure at least one phase exists for this contest
            $phaseRepo = $this->em->getRepository(\App\Entity\Phase::class);
            $phases = $phaseRepo->findBy(['contest' => $contest], ['phase_order' => 'ASC']);
            if (count($phases) === 0) {
                $defaultPhase = new \App\Entity\Phase();
                $defaultPhase->setContest($contest);
                $defaultPhase->setName('Default Phase');
                $defaultPhase->setPhaseOrder(1);
                $defaultPhase->setAllowSubmit(true);
                $defaultPhase->setAllowManualJudge(true);
                $defaultPhase->setAllowAutomaticJudge(true);
                $this->em->persist($defaultPhase);
                $this->em->flush();
            }

            // Pre-populate metaData with one empty row if none exists, so the form always shows at least one
            $metaData = $displayData->getMetaData();
            if (empty($metaData)) {
                $displayData->setMetaData(['' => '']);
            }

            $form = $this->createForm(ContestDisplayDataType::class, $displayData);
            $form->handleRequest($request);

            $mediaSnippet = null;
            if ($form->isSubmitted() && $form->isValid()) {
                // Handle banner file upload and delete previous banner if needed
                try {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/hackathon_banners';
                    if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0775, true)) {
                        throw new \RuntimeException('Failed to create banners upload directory.');
                    }
                    $bannerFile = $form->get('bannerFile')->getData();
                    if ($bannerFile) {
                        $prevBanner = $displayData->getBannerUrl();
                        if ($prevBanner && str_starts_with($prevBanner, '/uploads/hackathon_banners/')) {
                            $prevBannerPath = $this->getParameter('kernel.project_dir') . '/public' . $prevBanner;
                            if (is_file($prevBannerPath) && !@unlink($prevBannerPath)) {
                                $this->addFlash('warning', 'Could not delete previous banner image.');
                            }
                        }
                        $safeName = 'banner_' . $contest->getCid() . '_' . uniqid() . '.' . $bannerFile->guessExtension();
                        $bannerFile->move($uploadsDir, $safeName);
                        $displayData->setBannerUrl('/uploads/hackathon_banners/' . $safeName);
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Banner upload failed: ' . $e->getMessage());
                }

                // Handle media file upload (image/video)
                try {
                    $mediaFile = $form->get('mediaFile')->getData();
                    if ($mediaFile) {
                        $mediaDir = $this->getParameter('kernel.project_dir') . '/public/uploads/hackathon_media';
                        if (!is_dir($mediaDir) && !@mkdir($mediaDir, 0775, true)) {
                            throw new \RuntimeException('Failed to create media upload directory.');
                        }
                        $safeName = 'media_' . $contest->getCid() . '_' . uniqid() . '.' . $mediaFile->guessExtension();
                        $mime = $mediaFile->getMimeType(); // Get MIME type BEFORE move
                        $mediaFile->move($mediaDir, $safeName);
                        $mediaUrl = '/uploads/hackathon_media/' . $safeName;
                        if (str_starts_with($mime, 'image/')) {
                            $mediaSnippet = '<img src="' . $mediaUrl . '" alt="Media">';
                        } elseif (str_starts_with($mime, 'video/')) {
                            $mediaSnippet = '<video src="' . $mediaUrl . '" controls></video>';
                        } else {
                            $mediaSnippet = $mediaUrl;
                        }
                        // Append to metaData for tracking
                        $metaData = $displayData->getMetaData() ?: [];
                        $mediaType = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'other');
                        $metaData[] = [
                            'type' => 'media',
                            'path' => $mediaUrl,
                            'mediaType' => $mediaType,
                            'uploadedAt' => (new \DateTime())->format('c'),
                        ];
                        $displayData->setMetaData($metaData);
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Media upload failed: ' . $e->getMessage());
                }

                try {
                    $this->em->persist($displayData);
                    $this->em->flush();
                    $this->addFlash('success', 'Display data saved.');
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Failed to save display data: ' . $e->getMessage());
                }
                // If media was uploaded, show snippet after redirect
                if ($mediaSnippet) {
                    $request->getSession()->set('mediaSnippet', $mediaSnippet);
                }
                return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
            }

            // Show media snippet if available in session
            $session = $request->getSession();
            $mediaSnippet = $mediaSnippet ?? $session->get('mediaSnippet');
            if ($mediaSnippet) {
                $session->remove('mediaSnippet');
            }
            // Fetch phases for this contest
            $phases = $this->em->getRepository(\App\Entity\Phase::class)->findBy([
                'contest' => $contest
            ], ['phase_order' => 'ASC']);

            return $this->render('extensions_plugin/hackathon_display.html.twig', [
                'contest' => $contest,
                'form' => $form->createView(),
                'displayData' => $displayData,
                'mediaSnippet' => $mediaSnippet,
                'phases' => $phases,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Unexpected error: ' . $e->getMessage());
            return $this->redirectToRoute('jury_hackathon');
        }
    }
    #[Route(path: '/quick-add', name: 'jury_hackathon_quick_add')]
    public function quickAddHackathon(): Response
    {
        // Create a new Contest entity with some default values
        $contest = new Contest();
        $now = time();
        $contest->setName('New Hackathon ' . date('Y-m-d H:i', $now));
        $contest->setShortname('hackathon_' . $now);
        $contest->setStarttime(date('Y-m-d H:i:s', $now + 3600)); // Start in 1 hour
        $contest->setEndtime(date('Y-m-d H:i:s', $now + 3600 * 4)); // End in 4 hours
        $contest->setActivatetime(date('Y-m-d H:i:s', $now));
        $contest->setEnabled(true);
        $contest->setAllowSubmit(true);

        $this->em->persist($contest);
        $this->em->flush();

        $this->addFlash('success', 'Hackathon contest created!');
        // Redirect to the display config page for the new contest
        return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contest->getCid()]);
    }
    
        #[Route(path: '/{contestId<\d+>}/problems', name: 'jury_hackathon_problems')]
    public function problemsTab(int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        // Fetch problems for this contest
        $problems = $this->em->createQueryBuilder()
            ->select('cp', 'p')
            ->from('App\\Entity\\ContestProblem', 'cp')
            ->leftJoin('cp.problem', 'p')
            ->where('cp.contest = :contest')
            ->setParameter('contest', $contest)
            ->orderBy('cp.shortname', 'ASC')
            ->getQuery()->getResult();

        return $this->render('extensions_plugin/hackathon_problems.html.twig', [
            'contest' => $contest,
            'problems' => $problems,
        ]);
    }

    #[Route(path: '/{contestId<\d+>}/problems/quickadd', name: 'jury_hackathon_quickadd_problem', methods: ['POST'])]
    public function quickAddProblem(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $shortname = trim($request->request->get('shortname', ''));
        $name = trim($request->request->get('name', ''));
        $attachment = $request->files->get('attachment');

        // Count existing problems for this contest
        $problemCount = $this->em->getRepository(\App\Entity\ContestProblem::class)
            ->count(['contest' => $contest]);

        $autoShortname = 'P' . ($problemCount + 1);
        $autoName = 'New Problem ' . date('Y-m-d H:i');
        $finalShortname = $shortname !== '' ? $shortname : $autoShortname;
        $finalName = $name !== '' ? $name : $autoName;

        $problem = new \App\Entity\Problem();
        $problem->setName($finalName);
        $problem->setTimelimit(2.0);
        $problem->setMemlimit(262144); // 256 MB default
        $this->em->persist($problem);

        $contestProblem = new \App\Entity\ContestProblem();
        $contestProblem->setContest($contest);
        $contestProblem->setProblem($problem);
        $contestProblem->setShortname($finalShortname);
        $this->em->persist($contestProblem);

        // Create ProblemDisplayData
        $displayData = new \App\Entity\ProblemDisplayData();
        $displayData->setProblem($problem);
        $displayData->setDisplayName($finalShortname);
        $descTemplate = '<h2>' . htmlspecialchars($finalName) . '</h2>' .
            "\n<p><strong>Description:</strong><br>Describe the problem statement here. Explain what the task is and any background information.</p>" .
            "\n<p><strong>Input</strong><br>Describe the input format and constraints.</p>" .
            "\n<p><strong>Output</strong><br>Describe the output format and requirements.</p>" .
            "\n<p><strong>Sample Input</strong><br><pre>1 2 3\n4 5 6</pre></p>" .
            "\n<p><strong>Sample Output</strong><br><pre>6\n15</pre></p>";
        $displayData->setDescription($descTemplate);

        // Handle attachment upload (like hackathon display data)
        $attachments = [];
        if ($attachment) {
            try {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/problem_attachments';
                if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0775, true)) {
                    throw new \RuntimeException('Failed to create attachments upload directory.');
                }
                $safeName = 'attachment_' . $contest->getCid() . '_' . uniqid() . '.' . $attachment->guessExtension();
                $attachment->move($uploadsDir, $safeName);
                $attachments[] = [
                    'name' => $attachment->getClientOriginalName(),
                    'url' => '/uploads/problem_attachments/' . $safeName,
                    'type' => $attachment->getClientMimeType(),
                    'uploadedAt' => (new \DateTime())->format('c'),
                ];
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Attachment upload failed: ' . $e->getMessage());
            }
        }
        if ($attachments) {
            $displayData->setAttachments($attachments);
        }

        $this->em->persist($displayData);
        $this->em->flush();

        $this->addFlash('success', 'Problem added!');
        return $this->redirectToRoute('jury_hackathon_problems', ['contestId' => $contestId]);
    }
    #[Route(path: '/{contestId<\\d+>}/problems/{problemId<\\d+>}/edit-display', name: 'jury_hackathon_edit_problem_display')]
    public function editProblemDisplayData(Request $request, int $contestId, int $problemId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $problem = $this->em->getRepository(\App\Entity\Problem::class)->find($problemId);
        if (!$problem) {
            $this->addFlash('danger', 'Problem not found.');
            return $this->redirectToRoute('jury_hackathon_problems', ['contestId' => $contestId]);
        }

        $repo = $this->em->getRepository(\App\Entity\ProblemDisplayData::class);
        $displayData = $repo->findOneBy(['problem' => $problem]);
        if (!$displayData) {
            $displayData = new \App\Entity\ProblemDisplayData();
            $displayData->setProblem($problem);
        }

        $form = $this->createForm(\App\Form\Type\ProblemDisplayDataType::class, $displayData);
        $form->handleRequest($request);

        // Handle remove attachment request
        if ($request->isMethod('POST') && $request->request->has('remove_attachment')) {
            $removeIdx = (int)$request->request->get('remove_attachment');
            $attachments = $displayData->getAttachments() ?: [];
            if (isset($attachments[$removeIdx])) {
                // Optionally delete the file from disk here
                unset($attachments[$removeIdx]);
                $attachments = array_values($attachments); // reindex
                $displayData->setAttachments($attachments);
                $this->em->persist($displayData);
                $this->em->flush();
                $this->addFlash('success', 'Attachment removed.');
                return $this->redirectToRoute('jury_hackathon_edit_problem_display', ['contestId' => $contestId, 'problemId' => $problemId]);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $attachments = $displayData->getAttachments() ?: [];
            // Handle file upload (any file type)
            $attachmentFile = $form->get('attachmentFile')->getData();
            if ($attachmentFile) {
                try {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/problem_attachments';
                    if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0775, true)) {
                        throw new \RuntimeException('Failed to create attachments upload directory.');
                    }
                    $safeName = 'attachment_' . $problem->getProbid() . '_' . uniqid() . '.' . $attachmentFile->guessExtension();
                    $mime = $attachmentFile->getMimeType();
                    $attachmentFile->move($uploadsDir, $safeName);
                    $attachments[] = [
                        'name' => $attachmentFile->getClientOriginalName(),
                        'url' => '/uploads/problem_attachments/' . $safeName,
                        'type' => $mime,
                        'uploadedAt' => (new \DateTime())->format('c'),
                    ];
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Attachment upload failed: ' . $e->getMessage());
                }
            }
            // Handle link attachment
            $attachmentLink = $form->get('attachmentLink')->getData();
            if ($attachmentLink) {
                $attachments[] = [
                    'name' => parse_url($attachmentLink, PHP_URL_HOST) ?: $attachmentLink,
                    'url' => $attachmentLink,
                    'type' => 'link',
                    'uploadedAt' => (new \DateTime())->format('c'),
                ];
            }
            $displayData->setAttachments($attachments);
            $this->em->persist($displayData);
            $this->em->flush();
            $this->addFlash('success', 'Problem display data saved.');
            return $this->redirectToRoute('jury_hackathon_edit_problem_display', ['contestId' => $contestId, 'problemId' => $problemId]);
        }

        return $this->render('extensions_plugin/edit_problem_display.html.twig', [
            'contest' => $contest,
            'problem' => $problem,
            'form' => $form->createView(),
            'displayData' => $displayData,
        ]);
    }
}
