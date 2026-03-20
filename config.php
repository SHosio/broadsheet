<?php
// ─── Master Feed List ─────────────────────────────────────────────────────────
// id       : short stable slug — used in localStorage and URLs, never change these
// category : group label shown in the settings panel
// col      : default column (0–3)
// default  : shown on first visit if true
// limit    : max posts to fetch
//
// Default column layout:
//   0 → Academia         1 → ML / AI Reddit
//   2 → arXiv papers     3 → HCI / Health

$feeds = [

    // ── Reddit · Academia ────────────────────────────────────────────────────
    ['id' => 'r-phd',   'category' => 'Reddit · Academia',       'col' => 0, 'default' => true,  'limit' => 12, 'title' => 'r/PhD',                 'url' => 'https://www.reddit.com/r/PhD/hot.rss'],
    ['id' => 'r-prof',  'category' => 'Reddit · Academia',       'col' => 0, 'default' => true,  'limit' => 10, 'title' => 'r/Professors',          'url' => 'https://www.reddit.com/r/Professors/hot.rss'],
    ['id' => 'r-acad',  'category' => 'Reddit · Academia',       'col' => 0, 'default' => true,  'limit' => 10, 'title' => 'r/academia',            'url' => 'https://www.reddit.com/r/academia/hot.rss'],
    ['id' => 'r-grad',  'category' => 'Reddit · Academia',       'col' => 0, 'default' => false, 'limit' => 10, 'title' => 'r/GradSchool',          'url' => 'https://www.reddit.com/r/GradSchool/hot.rss'],
    ['id' => 'r-askac', 'category' => 'Reddit · Academia',       'col' => 0, 'default' => false, 'limit' => 10, 'title' => 'r/AskAcademia',         'url' => 'https://www.reddit.com/r/AskAcademia/hot.rss'],

    // ── Reddit · ML / AI ─────────────────────────────────────────────────────
    ['id' => 'r-ml',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => true,  'limit' => 15, 'title' => 'r/MachineLearning',     'url' => 'https://www.reddit.com/r/MachineLearning/hot.rss'],
    ['id' => 'r-ai',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => true,  'limit' => 10, 'title' => 'r/artificial',          'url' => 'https://www.reddit.com/r/artificial/hot.rss'],
    ['id' => 'r-dl',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => true,  'limit' => 10, 'title' => 'r/deeplearning',        'url' => 'https://www.reddit.com/r/deeplearning/hot.rss'],
    ['id' => 'r-llama', 'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => true,  'limit' => 12, 'title' => 'r/LocalLLaMA',          'url' => 'https://www.reddit.com/r/LocalLLaMA/hot.rss'],
    ['id' => 'r-cs',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => true,  'limit' => 10, 'title' => 'r/compsci',             'url' => 'https://www.reddit.com/r/compsci/hot.rss'],
    ['id' => 'r-ds',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => false, 'limit' => 10, 'title' => 'r/datascience',         'url' => 'https://www.reddit.com/r/datascience/hot.rss'],
    ['id' => 'r-lt',    'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => false, 'limit' => 10, 'title' => 'r/LanguageTechnology',  'url' => 'https://www.reddit.com/r/LanguageTechnology/hot.rss'],
    ['id' => 'r-lml',   'category' => 'Reddit · ML / AI',        'col' => 1, 'default' => false, 'limit' => 10, 'title' => 'r/learnmachinelearning','url' => 'https://www.reddit.com/r/learnmachinelearning/hot.rss'],

    // ── arXiv ────────────────────────────────────────────────────────────────
    ['id' => 'ax-cl',   'category' => 'arXiv',                   'col' => 2, 'default' => true,  'limit' => 15, 'title' => 'arXiv · cs.CL — Language',   'url' => 'http://export.arxiv.org/rss/cs.CL'],
    ['id' => 'ax-lg',   'category' => 'arXiv',                   'col' => 2, 'default' => true,  'limit' => 10, 'title' => 'arXiv · cs.LG — ML',         'url' => 'http://export.arxiv.org/rss/cs.LG'],
    ['id' => 'ax-ai',   'category' => 'arXiv',                   'col' => 2, 'default' => true,  'limit' => 10, 'title' => 'arXiv · cs.AI',              'url' => 'http://export.arxiv.org/rss/cs.AI'],
    ['id' => 'ax-ir',   'category' => 'arXiv',                   'col' => 2, 'default' => true,  'limit' => 8,  'title' => 'arXiv · cs.IR — Retrieval',  'url' => 'http://export.arxiv.org/rss/cs.IR'],
    ['id' => 'ax-cv',   'category' => 'arXiv',                   'col' => 2, 'default' => false, 'limit' => 8,  'title' => 'arXiv · cs.CV — Vision',     'url' => 'http://export.arxiv.org/rss/cs.CV'],
    ['id' => 'ax-ro',   'category' => 'arXiv',                   'col' => 2, 'default' => false, 'limit' => 8,  'title' => 'arXiv · cs.RO — Robotics',   'url' => 'http://export.arxiv.org/rss/cs.RO'],
    ['id' => 'ax-se',   'category' => 'arXiv',                   'col' => 2, 'default' => false, 'limit' => 8,  'title' => 'arXiv · cs.SE — Software',   'url' => 'http://export.arxiv.org/rss/cs.SE'],

    // ── Reddit · Neuro / Health ──────────────────────────────────────────────
    ['id' => 'r-cog',   'category' => 'Reddit · Neuro / Health', 'col' => 3, 'default' => true,  'limit' => 8,  'title' => 'r/cogsci',              'url' => 'https://www.reddit.com/r/cogsci/hot.rss'],
    ['id' => 'r-neuro', 'category' => 'Reddit · Neuro / Health', 'col' => 3, 'default' => true,  'limit' => 8,  'title' => 'r/neuroscience',        'url' => 'https://www.reddit.com/r/neuroscience/hot.rss'],
    ['id' => 'r-psych', 'category' => 'Reddit · Neuro / Health', 'col' => 3, 'default' => true,  'limit' => 8,  'title' => 'r/psychology',          'url' => 'https://www.reddit.com/r/psychology/hot.rss'],
    ['id' => 'r-psy2',  'category' => 'Reddit · Neuro / Health', 'col' => 3, 'default' => false, 'limit' => 8,  'title' => 'r/psychiatry',          'url' => 'https://www.reddit.com/r/psychiatry/hot.rss'],
    ['id' => 'r-dh',    'category' => 'Reddit · Neuro / Health', 'col' => 3, 'default' => false, 'limit' => 8,  'title' => 'r/digitalhealth',       'url' => 'https://www.reddit.com/r/digitalhealth/hot.rss'],

    // ── arXiv · HCI / Health ─────────────────────────────────────────────────
    ['id' => 'ax-hc',   'category' => 'arXiv',                   'col' => 3, 'default' => true,  'limit' => 15, 'title' => 'arXiv · cs.HC — HCI',        'url' => 'http://export.arxiv.org/rss/cs.HC'],
    ['id' => 'ax-cy',   'category' => 'arXiv',                   'col' => 3, 'default' => true,  'limit' => 10, 'title' => 'arXiv · cs.CY — Society',    'url' => 'http://export.arxiv.org/rss/cs.CY'],
    ['id' => 'ax-ne',   'category' => 'arXiv',                   'col' => 3, 'default' => false, 'limit' => 8,  'title' => 'arXiv · cs.NE — Neural/Evo', 'url' => 'http://export.arxiv.org/rss/cs.NE'],
    ['id' => 'ax-nc',   'category' => 'arXiv',                   'col' => 3, 'default' => false, 'limit' => 8,  'title' => 'arXiv · q-bio.NC — Neurons', 'url' => 'http://export.arxiv.org/rss/q-bio.NC'],
];
