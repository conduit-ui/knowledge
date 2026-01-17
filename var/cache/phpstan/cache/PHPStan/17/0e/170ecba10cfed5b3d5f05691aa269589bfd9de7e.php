<?php

declare(strict_types=1);

// odsl-/Users/jordanpartridge/packages/conduit-ui/knowledge/app
return \PHPStan\Cache\CacheItem::__set_state([
    'variableKey' => 'v1',
    'data' => [
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/DockerServiceInterface.php' => [
            0 => '77edbd23572e4524411ddef19cea987ea1a38e3c',
            1 => [
                0 => 'app\\contracts\\dockerserviceinterface',
            ],
            2 => [
                0 => 'app\\contracts\\isinstalled',
                1 => 'app\\contracts\\isrunning',
                2 => 'app\\contracts\\gethostos',
                3 => 'app\\contracts\\getinstallurl',
                4 => 'app\\contracts\\compose',
                5 => 'app\\contracts\\checkendpoint',
                6 => 'app\\contracts\\getversion',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/ChromaDBClientInterface.php' => [
            0 => '7000253f8e3d8f985fe0d95f5ba97cfaab547d9d',
            1 => [
                0 => 'app\\contracts\\chromadbclientinterface',
            ],
            2 => [
                0 => 'app\\contracts\\getorcreatecollection',
                1 => 'app\\contracts\\add',
                2 => 'app\\contracts\\query',
                3 => 'app\\contracts\\delete',
                4 => 'app\\contracts\\update',
                5 => 'app\\contracts\\isavailable',
                6 => 'app\\contracts\\getall',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/EmbeddingServiceInterface.php' => [
            0 => 'efadd45bd5bf983af57087ae0a15d9bd669747a0',
            1 => [
                0 => 'app\\contracts\\embeddingserviceinterface',
            ],
            2 => [
                0 => 'app\\contracts\\generate',
                1 => 'app\\contracts\\similarity',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/FullTextSearchInterface.php' => [
            0 => 'ddba003f62298e7357cc664e00603d9c07b53a4a',
            1 => [
                0 => 'app\\contracts\\fulltextsearchinterface',
            ],
            2 => [
                0 => 'app\\contracts\\searchobservations',
                1 => 'app\\contracts\\isavailable',
                2 => 'app\\contracts\\rebuildindex',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Providers/AppServiceProvider.php' => [
            0 => '269b8df1997b095a41ad9cc39855ea55eebe5ef3',
            1 => [
                0 => 'app\\providers\\appserviceprovider',
            ],
            2 => [
                0 => 'app\\providers\\boot',
                1 => 'app\\providers\\register',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Enums/ObservationType.php' => [
            0 => 'fdfa7e572143460674cbf08ba5d4fa8f11a9b652',
            1 => [
                0 => 'app\\enums\\observationtype',
            ],
            2 => [
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/CollectionCreationException.php' => [
            0 => '3dcbd626d4299eb576afa23b6e81adcc0af3f41c',
            1 => [
                0 => 'app\\exceptions\\qdrant\\collectioncreationexception',
            ],
            2 => [
                0 => 'app\\exceptions\\qdrant\\withreason',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/QdrantException.php' => [
            0 => '06e6ae7407d18426f6e9a964a5836835af76209f',
            1 => [
                0 => 'app\\exceptions\\qdrant\\qdrantexception',
            ],
            2 => [
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/UpsertException.php' => [
            0 => '396618aeda3046d09774d251392d591f1137e89d',
            1 => [
                0 => 'app\\exceptions\\qdrant\\upsertexception',
            ],
            2 => [
                0 => 'app\\exceptions\\qdrant\\withreason',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/ConnectionException.php' => [
            0 => 'e9f64cace7e53ea9c046ad2bbfbbcd5e9c2ad7bf',
            1 => [
                0 => 'app\\exceptions\\qdrant\\connectionexception',
            ],
            2 => [
                0 => 'app\\exceptions\\qdrant\\withmessage',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/CollectionNotFoundException.php' => [
            0 => '83e24d9f2bbc12080a2e202354df6e13056c462d',
            1 => [
                0 => 'app\\exceptions\\qdrant\\collectionnotfoundexception',
            ],
            2 => [
                0 => 'app\\exceptions\\qdrant\\forcollection',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Exceptions/Qdrant/EmbeddingException.php' => [
            0 => 'bd7ada14342c3dfb058817947adb20bddc06f800',
            1 => [
                0 => 'app\\exceptions\\qdrant\\embeddingexception',
            ],
            2 => [
                0 => 'app\\exceptions\\qdrant\\generationfailed',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/CreateCollection.php' => [
            0 => '4eb16903318bf987f8dc682b6cf11fe38710df96',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\createcollection',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/GetCollectionInfo.php' => [
            0 => '848731660bb899fa01b953774bc2796186efc81d',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\getcollectioninfo',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/DeletePoints.php' => [
            0 => '6585fb0f0d5370ff300a8b81e5890d13cdb062e3',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\deletepoints',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/SearchPoints.php' => [
            0 => '5784ff9984afdfb66df5ccd896779be683d8f50b',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\searchpoints',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/UpsertPoints.php' => [
            0 => 'cebb42bba4726cd2e866c32960d104a5ab5da66d',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\upsertpoints',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/GetPoints.php' => [
            0 => 'e2248747a52a53f1b32eb45227385015da8b0157',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\getpoints',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/QdrantConnector.php' => [
            0 => '7f97e900ca6566edf377d16698fbc212a9ffc118',
            1 => [
                0 => 'app\\integrations\\qdrant\\qdrantconnector',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\__construct',
                1 => 'app\\integrations\\qdrant\\resolvebaseurl',
                2 => 'app\\integrations\\qdrant\\defaultheaders',
                3 => 'app\\integrations\\qdrant\\defaultconfig',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeStatsCommand.php' => [
            0 => '291283e895d1dfbd83499830880e805daef4f2f9',
            1 => [
                0 => 'app\\commands\\knowledgestatscommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
                1 => 'app\\commands\\renderdashboard',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/SyncCommand.php' => [
            0 => '4168d2864643c58e894ce975a5147576224fadd8',
            1 => [
                0 => 'app\\commands\\synccommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
                1 => 'app\\commands\\getclient',
                2 => 'app\\commands\\createclient',
                3 => 'app\\commands\\pullfromcloud',
                4 => 'app\\commands\\pushtocloud',
                5 => 'app\\commands\\generateuniqueid',
                6 => 'app\\commands\\displaysummary',
                7 => 'app\\commands\\displaypullsummary',
                8 => 'app\\commands\\displaypushsummary',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeShowCommand.php' => [
            0 => 'f010c34fd4ecb3ad55590d25866e9be187faf602',
            1 => [
                0 => 'app\\commands\\knowledgeshowcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
                1 => 'app\\commands\\renderentry',
                2 => 'app\\commands\\colorize',
                3 => 'app\\commands\\prioritycolor',
                4 => 'app\\commands\\statuscolor',
                5 => 'app\\commands\\confidencecolor',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeGitContextCommand.php' => [
            0 => '2a353e88ed6db1f01a6b7f43a0032c5a33baddc2',
            1 => [
                0 => 'app\\commands\\knowledgegitcontextcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeSearchStatusCommand.php' => [
            0 => 'f8db7c7130edc0c487cc99825afa43f8d59c0dae',
            1 => [
                0 => 'app\\commands\\knowledgesearchstatuscommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/InstallCommand.php' => [
            0 => '279779bfd8f27337205ff63d0487faa8309551b7',
            1 => [
                0 => 'app\\commands\\installcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeExportCommand.php' => [
            0 => '0fbdda18a91aa29a141eb772452740db9725bbc9',
            1 => [
                0 => 'app\\commands\\knowledgeexportcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeSearchCommand.php' => [
            0 => 'ff3736e75e14fb37fedac8ca2ed28ae5f0dfef93',
            1 => [
                0 => 'app\\commands\\knowledgesearchcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeServeCommand.php' => [
            0 => 'd7fca108d702738cb8f744a96180db647d3b4a58',
            1 => [
                0 => 'app\\commands\\knowledgeservecommand',
            ],
            2 => [
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
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeAddCommand.php' => [
            0 => '2200ae859dd4af61a43ef0273640dc88bb4b68e0',
            1 => [
                0 => 'app\\commands\\knowledgeaddcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeExportAllCommand.php' => [
            0 => 'c3bc61342b691a478e79fcb3da33fe707b8e7c78',
            1 => [
                0 => 'app\\commands\\knowledgeexportallcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
                1 => 'app\\commands\\generatefilename',
                2 => 'app\\commands\\slugify',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeConfigCommand.php' => [
            0 => '16fddf14be8f7467ea3b685d776e0186d81809d6',
            1 => [
                0 => 'app\\commands\\knowledgeconfigcommand',
            ],
            2 => [
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
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/LogsCommand.php' => [
            0 => '282602756510a2012b9bb7f2c501fb25d93480c3',
            1 => [
                0 => 'app\\commands\\service\\logscommand',
            ],
            2 => [
                0 => 'app\\commands\\service\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/UpCommand.php' => [
            0 => '1dbf38efa30b9dc6a11edaa593d1b9de760d1737',
            1 => [
                0 => 'app\\commands\\service\\upcommand',
            ],
            2 => [
                0 => 'app\\commands\\service\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/DownCommand.php' => [
            0 => 'a60bbaa6567138dbc78ac1ff022503bf3d19920b',
            1 => [
                0 => 'app\\commands\\service\\downcommand',
            ],
            2 => [
                0 => 'app\\commands\\service\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/Service/StatusCommand.php' => [
            0 => '16da4890c1110f36cd7a1c29581c2e7960a1f720',
            1 => [
                0 => 'app\\commands\\service\\statuscommand',
            ],
            2 => [
                0 => 'app\\commands\\service\\__construct',
                1 => 'app\\commands\\service\\handle',
                2 => 'app\\commands\\service\\getcontainerstatus',
                3 => 'app\\commands\\service\\renderdashboard',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeArchiveCommand.php' => [
            0 => '31219e1f83ee36e3d86fe6896f743f081d90e2ef',
            1 => [
                0 => 'app\\commands\\knowledgearchivecommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
                1 => 'app\\commands\\archiveentry',
                2 => 'app\\commands\\restoreentry',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeListCommand.php' => [
            0 => 'c9f1c58d0b14475f69c9d9b5e5beeca13ed0b096',
            1 => [
                0 => 'app\\commands\\knowledgelistcommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeValidateCommand.php' => [
            0 => '88ac1f8fc1d72e938068cfd3ee653a55166a9963',
            1 => [
                0 => 'app\\commands\\knowledgevalidatecommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/PullRequestService.php' => [
            0 => '92cd716e9692f028482bf24a1d904af5391aba1d',
            1 => [
                0 => 'app\\services\\pullrequestservice',
            ],
            2 => [
                0 => 'app\\services\\create',
                1 => 'app\\services\\builddescription',
                2 => 'app\\services\\getcurrentcoverage',
                3 => 'app\\services\\commitchanges',
                4 => 'app\\services\\pushbranch',
                5 => 'app\\services\\formatcoveragesection',
                6 => 'app\\services\\extractprurl',
                7 => 'app\\services\\extractprnumber',
                8 => 'app\\services\\runcommand',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/MarkdownExporter.php' => [
            0 => '29ef5057e089d77ed0f8d22c0c2e006c15b678d5',
            1 => [
                0 => 'app\\services\\markdownexporter',
            ],
            2 => [
                0 => 'app\\services\\exportarray',
                1 => 'app\\services\\buildfrontmatterfromarray',
                2 => 'app\\services\\escapeyaml',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/GitContextService.php' => [
            0 => '824c08a27c774d93409c7468d0c786f1629d504e',
            1 => [
                0 => 'app\\services\\gitcontextservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\isgitrepository',
                2 => 'app\\services\\getrepositorypath',
                3 => 'app\\services\\getrepositoryurl',
                4 => 'app\\services\\getcurrentbranch',
                5 => 'app\\services\\getcurrentcommit',
                6 => 'app\\services\\getauthor',
                7 => 'app\\services\\getcontext',
                8 => 'app\\services\\rungitcommand',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/QdrantService.php' => [
            0 => '71da1c9864864ab99435ad63023b0037a947fd7f',
            1 => [
                0 => 'app\\services\\qdrantservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\ensurecollection',
                2 => 'app\\services\\upsert',
                3 => 'app\\services\\search',
                4 => 'app\\services\\scroll',
                5 => 'app\\services\\delete',
                6 => 'app\\services\\getbyid',
                7 => 'app\\services\\incrementusage',
                8 => 'app\\services\\updatefields',
                9 => 'app\\services\\getcachedembedding',
                10 => 'app\\services\\buildfilter',
                11 => 'app\\services\\count',
                12 => 'app\\services\\getcollectionname',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/KnowledgePathService.php' => [
            0 => '57996762c55f03a28dc99340471b1c57197c859d',
            1 => [
                0 => 'app\\services\\knowledgepathservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\getknowledgedirectory',
                2 => 'app\\services\\ensuredirectoryexists',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/DockerService.php' => [
            0 => '0ad7be8962d172615a38adf029a534dbb7890f1d',
            1 => [
                0 => 'app\\services\\dockerservice',
            ],
            2 => [
                0 => 'app\\services\\isinstalled',
                1 => 'app\\services\\isrunning',
                2 => 'app\\services\\gethostos',
                3 => 'app\\services\\getinstallurl',
                4 => 'app\\services\\compose',
                5 => 'app\\services\\checkendpoint',
                6 => 'app\\services\\getversion',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/StubEmbeddingService.php' => [
            0 => '4396ca033150412a588b1029752897c92ae8d0e6',
            1 => [
                0 => 'app\\services\\stubembeddingservice',
            ],
            2 => [
                0 => 'app\\services\\generate',
                1 => 'app\\services\\similarity',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/TestExecutorService.php' => [
            0 => 'caea430bf53bb53ec524384308a750f15e36bb08',
            1 => [
                0 => 'app\\services\\testexecutorservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\runtests',
                2 => 'app\\services\\parsefailures',
                3 => 'app\\services\\autofixfailure',
                4 => 'app\\services\\gettestfileforclass',
                5 => 'app\\services\\extractfilepath',
                6 => 'app\\services\\extracttestcount',
                7 => 'app\\services\\getimplementationfilefortest',
                8 => 'app\\services\\applyfix',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/StubFtsService.php' => [
            0 => '5ac512fc9b584bb95f2031d885762f1a9a684c99',
            1 => [
                0 => 'app\\services\\stubftsservice',
            ],
            2 => [
                0 => 'app\\services\\searchobservations',
                1 => 'app\\services\\isavailable',
                2 => 'app\\services\\rebuildindex',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/RuntimeEnvironment.php' => [
            0 => '27d5db176ba0e2c911b75f4de288303daae4ec02',
            1 => [
                0 => 'app\\services\\runtimeenvironment',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\isphar',
                2 => 'app\\services\\basepath',
                3 => 'app\\services\\cachepath',
                4 => 'app\\services\\resolvebasepath',
                5 => 'app\\services\\ensuredirectoryexists',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/ChromaDBClient.php' => [
            0 => 'ac780efdd736f967741f43d3793c049bf2b5a6d2',
            1 => [
                0 => 'app\\services\\chromadbclient',
            ],
            2 => [
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
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/IssueAnalyzerService.php' => [
            0 => 'a8dd72a415824d599b5477251f26493631991912',
            1 => [
                0 => 'app\\services\\issueanalyzerservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\analyzeissue',
                2 => 'app\\services\\buildtodolist',
                3 => 'app\\services\\gathercodebasecontext',
                4 => 'app\\services\\extractkeywords',
                5 => 'app\\services\\searchfiles',
                6 => 'app\\services\\validateandenhanceanalysis',
                7 => 'app\\services\\groupfilesbychangetype',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/QualityGateService.php' => [
            0 => '5b7948bf307c511b305f3f2e9994cccae91ea963',
            1 => [
                0 => 'app\\services\\qualitygateservice',
            ],
            2 => [
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
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/TodoExecutorService.php' => [
            0 => 'ed1a9da974dfdc7fcc366289a0635962c574dda3',
            1 => [
                0 => 'app\\services\\todoexecutorservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\execute',
                2 => 'app\\services\\executeimplementation',
                3 => 'app\\services\\executetest',
                4 => 'app\\services\\executequality',
                5 => 'app\\services\\shouldcommitmilestone',
                6 => 'app\\services\\commitmilestone',
                7 => 'app\\services\\getcompletedtodos',
                8 => 'app\\services\\getfailedtodos',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/OllamaService.php' => [
            0 => '27199ab158d4a0ce7c6e6d315419818cd4eaa2d6',
            1 => [
                0 => 'app\\services\\ollamaservice',
            ],
            2 => [
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
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/ChromaDBEmbeddingService.php' => [
            0 => '39adede2419965fd4aae3b84fff5572c2e6aba89',
            1 => [
                0 => 'app\\services\\chromadbembeddingservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\generate',
                2 => 'app\\services\\similarity',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Integrations/Qdrant/Requests/ScrollPoints.php' => [
            0 => '80d3a3d4e9cd2db26f0b424c0bd07f416845ac09',
            1 => [
                0 => 'app\\integrations\\qdrant\\requests\\scrollpoints',
            ],
            2 => [
                0 => 'app\\integrations\\qdrant\\requests\\__construct',
                1 => 'app\\integrations\\qdrant\\requests\\resolveendpoint',
                2 => 'app\\integrations\\qdrant\\requests\\defaultbody',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Commands/KnowledgeUpdateCommand.php' => [
            0 => 'f46744b6fd773559186dd7249ca36288fd0f6fa7',
            1 => [
                0 => 'app\\commands\\knowledgeupdatecommand',
            ],
            2 => [
                0 => 'app\\commands\\handle',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Contracts/HealthCheckInterface.php' => [
            0 => 'dedc1f04e6f70dfa5d740821f86f1f6ef3cf4a01',
            1 => [
                0 => 'app\\contracts\\healthcheckinterface',
            ],
            2 => [
                0 => 'app\\contracts\\check',
                1 => 'app\\contracts\\checkall',
                2 => 'app\\contracts\\getservices',
            ],
            3 => [
            ],
        ],
        '/Users/jordanpartridge/packages/conduit-ui/knowledge/app/Services/HealthCheckService.php' => [
            0 => 'e4d3ce864534802b3eb0bdc969ddd48f954952a1',
            1 => [
                0 => 'app\\services\\healthcheckservice',
            ],
            2 => [
                0 => 'app\\services\\__construct',
                1 => 'app\\services\\check',
                2 => 'app\\services\\checkall',
                3 => 'app\\services\\getservices',
                4 => 'app\\services\\getendpoint',
                5 => 'app\\services\\checkqdrant',
                6 => 'app\\services\\checkredis',
                7 => 'app\\services\\checkembeddings',
                8 => 'app\\services\\checkollama',
                9 => 'app\\services\\httpcheck',
            ],
            3 => [
            ],
        ],
    ],
]);
