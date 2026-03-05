<?php

use App\Mcp\Servers\KnowledgeServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('knowledge', KnowledgeServer::class);
