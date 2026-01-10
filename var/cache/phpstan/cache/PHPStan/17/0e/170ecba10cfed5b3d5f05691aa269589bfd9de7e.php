<?php declare(strict_types = 1);

// odsl-/Users/jordanpartridge/packages/conduit-ui/knowledge/app
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1',
   'data' => 
  array (
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/DockerServiceInterface.php' => 
    array (
      0 => '77edbd23572e4524411ddef19cea987ea1a38e3c',
      1 => 
      array (
        0 => 'app\\contracts\\dockerserviceinterface',
      ),
      2 => 
      array (
        0 => 'app\\contracts\\isinstalled',
        1 => 'app\\contracts\\isrunning',
        2 => 'app\\contracts\\gethostos',
        3 => 'app\\contracts\\getinstallurl',
        4 => 'app\\contracts\\compose',
        5 => 'app\\contracts\\checkendpoint',
        6 => 'app\\contracts\\getversion',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/ChromaDBClientInterface.php' => 
    array (
      0 => '7000253f8e3d8f985fe0d95f5ba97cfaab547d9d',
      1 => 
      array (
        0 => 'app\\contracts\\chromadbclientinterface',
      ),
      2 => 
      array (
        0 => 'app\\contracts\\getorcreatecollection',
        1 => 'app\\contracts\\add',
        2 => 'app\\contracts\\query',
        3 => 'app\\contracts\\delete',
        4 => 'app\\contracts\\update',
        5 => 'app\\contracts\\isavailable',
        6 => 'app\\contracts\\getall',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/EmbeddingServiceInterface.php' => 
    array (
      0 => 'efadd45bd5bf983af57087ae0a15d9bd669747a0',
      1 => 
      array (
        0 => 'app\\contracts\\embeddingserviceinterface',
      ),
      2 => 
      array (
        0 => 'app\\contracts\\generate',
        1 => 'app\\contracts\\similarity',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/FullTextSearchInterface.php' => 
    array (
      0 => 'ddba003f62298e7357cc664e00603d9c07b53a4a',
      1 => 
      array (
        0 => 'app\\contracts\\fulltextsearchinterface',
      ),
      2 => 
      array (
        0 => 'app\\contracts\\searchobservations',
        1 => 'app\\contracts\\isavailable',
        2 => 'app\\contracts\\rebuildindex',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Providers/AppServiceProvider.php' => 
    array (
      0 => '7fe21ed3dae617b23708bbbd73e49f8b1d64372e',
      1 => 
      array (
        0 => 'app\\providers\\appserviceprovider',
      ),
      2 => 
      array (
        0 => 'app\\providers\\boot',
        1 => 'app\\providers\\register',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Enums/ObservationType.php' => 
    array (
      0 => 'fdfa7e572143460674cbf08ba5d4fa8f11a9b652',
      1 => 
      array (
        0 => 'app\\enums\\observationtype',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Models/Session.php' => 
    array (
      0 => '11d7a5a1619eeda335835f392ea7693d2ec81dec',
      1 => 
      array (
        0 => 'app\\models\\session',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\observations',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Models/Tag.php' => 
    array (
      0 => '683f5c8d342924c8a73d15c8fc8db51c55c96d0e',
      1 => 
      array (
        0 => 'app\\models\\tag',
      ),
      2 => 
      array (
        0 => 'app\\models\\casts',
        1 => 'app\\models\\entries',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/CollectionCreationException.php' => 
    array (
      0 => '3dcbd626d4299eb576afa23b6e81adcc0af3f41c',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\collectioncreationexception',
      ),
      2 => 
      array (
        0 => 'app\\exceptions\\qdrant\\withreason',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/QdrantException.php' => 
    array (
      0 => '06e6ae7407d18426f6e9a964a5836835af76209f',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\qdrantexception',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/UpsertException.php' => 
    array (
      0 => '396618aeda3046d09774d251392d591f1137e89d',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\upsertexception',
      ),
      2 => 
      array (
        0 => 'app\\exceptions\\qdrant\\withreason',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/ConnectionException.php' => 
    array (
      0 => 'e9f64cace7e53ea9c046ad2bbfbbcd5e9c2ad7bf',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\connectionexception',
      ),
      2 => 
      array (
        0 => 'app\\exceptions\\qdrant\\withmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/CollectionNotFoundException.php' => 
    array (
      0 => '83e24d9f2bbc12080a2e202354df6e13056c462d',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\collectionnotfoundexception',
      ),
      2 => 
      array (
        0 => 'app\\exceptions\\qdrant\\forcollection',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/EmbeddingException.php' => 
    array (
      0 => 'bd7ada14342c3dfb058817947adb20bddc06f800',
      1 => 
      array (
        0 => 'app\\exceptions\\qdrant\\embeddingexception',
      ),
      2 => 
      array (
        0 => 'app\\exceptions\\qdrant\\generationfailed',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/CreateCollection.php' => 
    array (
      0 => '4eb16903318bf987f8dc682b6cf11fe38710df96',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\createcollection',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
        2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/GetCollectionInfo.php' => 
    array (
      0 => '848731660bb899fa01b953774bc2796186efc81d',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\getcollectioninfo',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/DeletePoints.php' => 
    array (
      0 => '4985ac01df30313875aa47f8a739e03f320f4aeb',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\deletepoints',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
        2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/SearchPoints.php' => 
    array (
      0 => '0e27a7d05357e029bcd6b1506a601d7880ac0050',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\searchpoints',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
        2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/UpsertPoints.php' => 
    array (
      0 => 'dee88f6fb0f2f26c4ca69360c38e85e8a299bc7b',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\upsertpoints',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
        2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/GetPoints.php' => 
    array (
      0 => '661fe32bb517e0e9ed6f9717d964a4bb47a8488d',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\getpoints',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\requests\\__construct',
        1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
        2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/QdrantConnector.php' => 
    array (
      0 => '7f97e900ca6566edf377d16698fbc212a9ffc118',
      1 => 
      array (
        0 => 'app\\integrations\\qdrant\\qdrantconnector',
      ),
      2 => 
      array (
        0 => 'app\\integrations\\qdrant\\__construct',
        1 => 'app\\integrations\\qdrant\\resolvebaseurl',
        2 => 'app\\integrations\\qdrant\\defaultheaders',
        3 => 'app\\integrations\\qdrant\\defaultconfig',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeStatsCommand.php' => 
    array (
      0 => 'dc0029e04abe8001f30da4cda807e2eb2c314b2a',
      1 => 
      array (
        0 => 'app\\commands\\knowledgestatscommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\displayoverview',
        2 => 'app\\commands\\displaystatusbreakdown',
        3 => 'app\\commands\\displaycategorybreakdown',
        4 => 'app\\commands\\displayusagestatistics',
        5 => 'app\\commands\\getallentries',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/SyncCommand.php' => 
    array (
      0 => '78a47f116766edc483835df7a62c71befc53d11b',
      1 => 
      array (
        0 => 'app\\commands\\synccommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\getclient',
        2 => 'app\\commands\\createclient',
        3 => 'app\\commands\\pullfromcloud',
        4 => 'app\\commands\\pushtocloud',
        5 => 'app\\commands\\generateuniqueid',
        6 => 'app\\commands\\displaysummary',
        7 => 'app\\commands\\displaypullsummary',
        8 => 'app\\commands\\displaypushsummary',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeShowCommand.php' => 
    array (
      0 => '00ac0b1edf07f8e4e7debe1657a024f2c5428e7d',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeshowcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeGitContextCommand.php' => 
    array (
      0 => '2a353e88ed6db1f01a6b7f43a0032c5a33baddc2',
      1 => 
      array (
        0 => 'app\\commands\\knowledgegitcontextcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeSearchStatusCommand.php' => 
    array (
      0 => '91f42a6a4661ecbb6559dbdd16fdc4402fa0de07',
      1 => 
      array (
        0 => 'app\\commands\\knowledgesearchstatuscommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/InstallCommand.php' => 
    array (
      0 => '9213693f80dd5a232116592b2206e4ef90d7b373',
      1 => 
      array (
        0 => 'app\\commands\\installcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeExportCommand.php' => 
    array (
      0 => '0fbdda18a91aa29a141eb772452740db9725bbc9',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeexportcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeSearchCommand.php' => 
    array (
      0 => 'd91714ef7be99e53f26b4bc71058b364a95b982b',
      1 => 
      array (
        0 => 'app\\commands\\knowledgesearchcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeServeCommand.php' => 
    array (
      0 => 'd7fca108d702738cb8f744a96180db647d3b4a58',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeservecommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\install',
        2 => 'app\\commands\\start',
        3 => 'app\\commands\\stop',
        4 => 'app\\commands\\status',
        5 => 'app\\commands\\restart',
        6 => 'app\\commands\\invalidaction',
        7 => 'app\\commands\\getconfigpath',
        8 => 'app\\commands\\hasconfig',
        9 => 'app\\commands\\getsourcepath',
        10 => 'app\\commands\\setupconfigdirectory',
        11 => 'app\\commands\\copydockerfiles',
        12 => 'app\\commands\\showdockerinstallinstructions',
        13 => 'app\\commands\\showendpoints',
        14 => 'app\\commands\\showpostinstallinfo',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeAddCommand.php' => 
    array (
      0 => 'f975e4edad231c092ed243dbc5731b1123404354',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeaddcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeExportAllCommand.php' => 
    array (
      0 => 'c3bc61342b691a478e79fcb3da33fe707b8e7c78',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeexportallcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\generatefilename',
        2 => 'app\\commands\\slugify',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeConfigCommand.php' => 
    array (
      0 => '16fddf14be8f7467ea3b685d776e0186d81809d6',
      1 => 
      array (
        0 => 'app\\commands\\knowledgeconfigcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\listconfig',
        2 => 'app\\commands\\getconfig',
        3 => 'app\\commands\\setconfig',
        4 => 'app\\commands\\invalidaction',
        5 => 'app\\commands\\loadconfig',
        6 => 'app\\commands\\saveconfig',
        7 => 'app\\commands\\getconfigpath',
        8 => 'app\\commands\\isvalidkey',
        9 => 'app\\commands\\getnestedvalue',
        10 => 'app\\commands\\setnestedvalue',
        11 => 'app\\commands\\parsevalue',
        12 => 'app\\commands\\validatevalue',
        13 => 'app\\commands\\isvalidurl',
        14 => 'app\\commands\\formatvalue',
        15 => 'app\\commands\\displayconfigtree',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/LogsCommand.php' => 
    array (
      0 => 'ae4c10562087105abf6985ff989c043c4da2c7af',
      1 => 
      array (
        0 => 'app\\commands\\service\\logscommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\service\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/UpCommand.php' => 
    array (
      0 => '701569481f11d5df2abbda3b1aacc9cf079fd9ba',
      1 => 
      array (
        0 => 'app\\commands\\service\\upcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\service\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/DownCommand.php' => 
    array (
      0 => '7aaafe3d558eb7fe72fafa3e57a8b7a1028f16d4',
      1 => 
      array (
        0 => 'app\\commands\\service\\downcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\service\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/StatusCommand.php' => 
    array (
      0 => '1c3d4291313bae2fbea2ecac82ef947e20588f19',
      1 => 
      array (
        0 => 'app\\commands\\service\\statuscommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\service\\handle',
        1 => 'app\\commands\\service\\performhealthchecks',
        2 => 'app\\commands\\service\\getcontainerstatus',
        3 => 'app\\commands\\service\\renderdashboard',
        4 => 'app\\commands\\service\\checkqdrant',
        5 => 'app\\commands\\service\\checkredis',
        6 => 'app\\commands\\service\\checkembeddings',
        7 => 'app\\commands\\service\\checkollama',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeArchiveCommand.php' => 
    array (
      0 => '31219e1f83ee36e3d86fe6896f743f081d90e2ef',
      1 => 
      array (
        0 => 'app\\commands\\knowledgearchivecommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
        1 => 'app\\commands\\archiveentry',
        2 => 'app\\commands\\restoreentry',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeListCommand.php' => 
    array (
      0 => 'db1718e98b3cc1458935a23a6c32a371f804fd53',
      1 => 
      array (
        0 => 'app\\commands\\knowledgelistcommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeValidateCommand.php' => 
    array (
      0 => '88ac1f8fc1d72e938068cfd3ee653a55166a9963',
      1 => 
      array (
        0 => 'app\\commands\\knowledgevalidatecommand',
      ),
      2 => 
      array (
        0 => 'app\\commands\\handle',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/PullRequestService.php' => 
    array (
      0 => 'd6eaba95c64a74369d2384494830915740460a85',
      1 => 
      array (
        0 => 'app\\services\\pullrequestservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\create',
        1 => 'app\\services\\builddescription',
        2 => 'app\\services\\getcurrentcoverage',
        3 => 'app\\services\\commitchanges',
        4 => 'app\\services\\pushbranch',
        5 => 'app\\services\\formatcoveragesection',
        6 => 'app\\services\\extractprurl',
        7 => 'app\\services\\extractprnumber',
        8 => 'app\\services\\runcommand',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/MarkdownExporter.php' => 
    array (
      0 => '29ef5057e089d77ed0f8d22c0c2e006c15b678d5',
      1 => 
      array (
        0 => 'app\\services\\markdownexporter',
      ),
      2 => 
      array (
        0 => 'app\\services\\exportarray',
        1 => 'app\\services\\buildfrontmatterfromarray',
        2 => 'app\\services\\escapeyaml',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/GitContextService.php' => 
    array (
      0 => '824c08a27c774d93409c7468d0c786f1629d504e',
      1 => 
      array (
        0 => 'app\\services\\gitcontextservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\isgitrepository',
        2 => 'app\\services\\getrepositorypath',
        3 => 'app\\services\\getrepositoryurl',
        4 => 'app\\services\\getcurrentbranch',
        5 => 'app\\services\\getcurrentcommit',
        6 => 'app\\services\\getauthor',
        7 => 'app\\services\\getcontext',
        8 => 'app\\services\\rungitcommand',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/ObservationService.php' => 
    array (
      0 => '73e9330e37faff3cec4a61df1a4d8387c37037ce',
      1 => 
      array (
        0 => 'app\\services\\observationservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\createobservation',
        2 => 'app\\services\\searchobservations',
        3 => 'app\\services\\getobservationsbytype',
        4 => 'app\\services\\getrecentobservations',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/QdrantService.php' => 
    array (
      0 => 'eee0ea02db403cbdaa9020c6d322bf17653fcf8d',
      1 => 
      array (
        0 => 'app\\services\\qdrantservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\ensurecollection',
        2 => 'app\\services\\upsert',
        3 => 'app\\services\\search',
        4 => 'app\\services\\delete',
        5 => 'app\\services\\getbyid',
        6 => 'app\\services\\incrementusage',
        7 => 'app\\services\\updatefields',
        8 => 'app\\services\\getcachedembedding',
        9 => 'app\\services\\buildfilter',
        10 => 'app\\services\\getcollectionname',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/KnowledgePathService.php' => 
    array (
      0 => '623b756d46dd406a925f944c1e7364a64a3ad90c',
      1 => 
      array (
        0 => 'app\\services\\knowledgepathservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\getknowledgedirectory',
        2 => 'app\\services\\getdatabasepath',
        3 => 'app\\services\\ensuredirectoryexists',
        4 => 'app\\services\\databaseexists',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/DatabaseInitializer.php' => 
    array (
      0 => 'f3e1c0d556505cb6150c95341c9b6cb7dbc708ff',
      1 => 
      array (
        0 => 'app\\services\\databaseinitializer',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\initialize',
        2 => 'app\\services\\isinitialized',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/DockerService.php' => 
    array (
      0 => '0ad7be8962d172615a38adf029a534dbb7890f1d',
      1 => 
      array (
        0 => 'app\\services\\dockerservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\isinstalled',
        1 => 'app\\services\\isrunning',
        2 => 'app\\services\\gethostos',
        3 => 'app\\services\\getinstallurl',
        4 => 'app\\services\\compose',
        5 => 'app\\services\\checkendpoint',
        6 => 'app\\services\\getversion',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/StubEmbeddingService.php' => 
    array (
      0 => '4396ca033150412a588b1029752897c92ae8d0e6',
      1 => 
      array (
        0 => 'app\\services\\stubembeddingservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\generate',
        1 => 'app\\services\\similarity',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/TestExecutorService.php' => 
    array (
      0 => 'caea430bf53bb53ec524384308a750f15e36bb08',
      1 => 
      array (
        0 => 'app\\services\\testexecutorservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\runtests',
        2 => 'app\\services\\parsefailures',
        3 => 'app\\services\\autofixfailure',
        4 => 'app\\services\\gettestfileforclass',
        5 => 'app\\services\\extractfilepath',
        6 => 'app\\services\\extracttestcount',
        7 => 'app\\services\\getimplementationfilefortest',
        8 => 'app\\services\\applyfix',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/StubFtsService.php' => 
    array (
      0 => '5ac512fc9b584bb95f2031d885762f1a9a684c99',
      1 => 
      array (
        0 => 'app\\services\\stubftsservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\searchobservations',
        1 => 'app\\services\\isavailable',
        2 => 'app\\services\\rebuildindex',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/RuntimeEnvironment.php' => 
    array (
      0 => '9575f9b860597d2ac34f2819cb463e17131b6893',
      1 => 
      array (
        0 => 'app\\services\\runtimeenvironment',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\isphar',
        2 => 'app\\services\\basepath',
        3 => 'app\\services\\databasepath',
        4 => 'app\\services\\cachepath',
        5 => 'app\\services\\resolvebasepath',
        6 => 'app\\services\\ensuredirectoryexists',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/ChromaDBClient.php' => 
    array (
      0 => 'ac780efdd736f967741f43d3793c049bf2b5a6d2',
      1 => 
      array (
        0 => 'app\\services\\chromadbclient',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\getcollectionspath',
        2 => 'app\\services\\getorcreatecollection',
        3 => 'app\\services\\add',
        4 => 'app\\services\\query',
        5 => 'app\\services\\delete',
        6 => 'app\\services\\update',
        7 => 'app\\services\\getcollectioncount',
        8 => 'app\\services\\isavailable',
        9 => 'app\\services\\getall',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/IssueAnalyzerService.php' => 
    array (
      0 => 'a8dd72a415824d599b5477251f26493631991912',
      1 => 
      array (
        0 => 'app\\services\\issueanalyzerservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\analyzeissue',
        2 => 'app\\services\\buildtodolist',
        3 => 'app\\services\\gathercodebasecontext',
        4 => 'app\\services\\extractkeywords',
        5 => 'app\\services\\searchfiles',
        6 => 'app\\services\\validateandenhanceanalysis',
        7 => 'app\\services\\groupfilesbychangetype',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/QualityGateService.php' => 
    array (
      0 => '5b7948bf307c511b305f3f2e9994cccae91ea963',
      1 => 
      array (
        0 => 'app\\services\\qualitygateservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\runallgates',
        1 => 'app\\services\\runtests',
        2 => 'app\\services\\checkcoverage',
        3 => 'app\\services\\runstaticanalysis',
        4 => 'app\\services\\applyformatting',
        5 => 'app\\services\\extracttestcount',
        6 => 'app\\services\\extracttesterrors',
        7 => 'app\\services\\extractcoveragepercentage',
        8 => 'app\\services\\extractphpstanerrors',
        9 => 'app\\services\\extractformattedfilescount',
        10 => 'app\\services\\buildsummary',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/TodoExecutorService.php' => 
    array (
      0 => 'ed1a9da974dfdc7fcc366289a0635962c574dda3',
      1 => 
      array (
        0 => 'app\\services\\todoexecutorservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\execute',
        2 => 'app\\services\\executeimplementation',
        3 => 'app\\services\\executetest',
        4 => 'app\\services\\executequality',
        5 => 'app\\services\\shouldcommitmilestone',
        6 => 'app\\services\\commitmilestone',
        7 => 'app\\services\\getcompletedtodos',
        8 => 'app\\services\\getfailedtodos',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/SQLiteFtsService.php' => 
    array (
      0 => '4008b6e18c0bb021d005e7703f9b5e192f7ee0a0',
      1 => 
      array (
        0 => 'app\\services\\sqliteftsservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\searchobservations',
        1 => 'app\\services\\isavailable',
        2 => 'app\\services\\rebuildindex',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/SessionService.php' => 
    array (
      0 => 'faad44ce118dbc60502c65bff99c61feae500f76',
      1 => 
      array (
        0 => 'app\\services\\sessionservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\getactivesessions',
        1 => 'app\\services\\getrecentsessions',
        2 => 'app\\services\\getsessionwithobservations',
        3 => 'app\\services\\getsessionobservations',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/OllamaService.php' => 
    array (
      0 => '35650e5cc448860a3c59616b64faf77bb5bc765a',
      1 => 
      array (
        0 => 'app\\services\\ollamaservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\enhanceentry',
        2 => 'app\\services\\extracttags',
        3 => 'app\\services\\categorize',
        4 => 'app\\services\\extractconcepts',
        5 => 'app\\services\\expandquery',
        6 => 'app\\services\\suggesttitle',
        7 => 'app\\services\\generate',
        8 => 'app\\services\\buildenhancementprompt',
        9 => 'app\\services\\parseenhancementresponse',
        10 => 'app\\services\\parsejsonresponse',
        11 => 'app\\services\\analyzeissue',
        12 => 'app\\services\\suggestcodechanges',
        13 => 'app\\services\\analyzetestfailure',
        14 => 'app\\services\\buildissueanalysisprompt',
        15 => 'app\\services\\parseissueanalysisresponse',
        16 => 'app\\services\\isavailable',
      ),
      3 => 
      array (
      ),
    ),
    '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/ChromaDBEmbeddingService.php' => 
    array (
      0 => '39adede2419965fd4aae3b84fff5572c2e6aba89',
      1 => 
      array (
        0 => 'app\\services\\chromadbembeddingservice',
      ),
      2 => 
      array (
        0 => 'app\\services\\__construct',
        1 => 'app\\services\\generate',
        2 => 'app\\services\\similarity',
      ),
      3 => 
      array (
      ),
    ),
  ),
));