# knowledge Documentation

> Generated on 2026-02-12T06:26:42+00:00

## Context Summary

Generated from 4 knowledge entries covering architecture, testing, debugging with an average confidence of 92.5%.

## Architecture

### Architecture

#### Architecture: Vector Storage

Knowledge CLI uses Qdrant as the primary vector storage backend. All knowledge entries are stored as embeddings with JSON payloads. The system supports hybrid search combining dense and sparse vectors for optimal retrieval accuracy.

**Tags**: architecture, qdrant, vectors, storage

**Confidence**: 95%

---



## General

### Testing

#### Setup: Docker Compose

Start Knowledge CLI services with: docker compose up -d. This launches Qdrant on port 6333 and the embedding server on port 8001. Ensure Docker is running and ports are available before starting.

**Tags**: setup, docker, compose, services

**Confidence**: 100%

---



## Debugging

### Debugging

#### Debugging: Qdrant Connection Issues

When experiencing Qdrant connection failures, first check if the service is running on port 6333. Verify network connectivity and ensure the collection exists. Common issues include firewall blocking the port or incorrect host configuration in .env file.

**Tags**: debugging, qdrant, connection, troubleshooting

**Confidence**: 85%

---

#### API Endpoint: Knowledge Search

The knowledge search endpoint provides semantic search capabilities across the knowledge base. It accepts query parameters for filtering by category, tags, and project namespace. Returns ranked results with confidence scores.

**Tags**: api, search, endpoint

**Confidence**: 90%

---



## Api Documentation

### API Endpoints and Services

#### Setup: Docker Compose

Start Knowledge CLI services with: docker compose up -d. This launches Qdrant on port 6333 and the embedding server on port 8001. Ensure Docker is running and ports are available before starting.

---

#### Debugging: Qdrant Connection Issues

When experiencing Qdrant connection failures, first check if the service is running on port 6333. Verify network connectivity and ensure the collection exists. Common issues include firewall blocking the port or incorrect host configuration in .env file.

---

#### API Endpoint: Knowledge Search

The knowledge search endpoint provides semantic search capabilities across the knowledge base. It accepts query parameters for filtering by category, tags, and project namespace. Returns ranked results with confidence scores.

**Module**: QdrantService

---



## Architecture Overview

### System Architecture

#### Qdrantservice

##### Architecture: Vector Storage

Knowledge CLI uses Qdrant as the primary vector storage backend. All knowledge entries are stored as embeddings with JSON payloads. The system supports hybrid search combining dense and sparse vectors for optimal retrieval accuracy.



## Debugging Guide

### Common Issues and Solutions

#### Debugging: Qdrant Connection Issues

When experiencing Qdrant connection failures, first check if the service is running on port 6333. Verify network connectivity and ensure the collection exists. Common issues include firewall blocking the port or incorrect host configuration in .env file.

---



## Setup Guide

### Setup and Configuration

#### Setup: Docker Compose

Start Knowledge CLI services with: docker compose up -d. This launches Qdrant on port 6333 and the embedding server on port 8001. Ensure Docker is running and ports are available before starting.

---

#### Debugging: Qdrant Connection Issues

When experiencing Qdrant connection failures, first check if the service is running on port 6333. Verify network connectivity and ensure the collection exists. Common issues include firewall blocking the port or incorrect host configuration in .env file.

---



---

## Metadata

- **Project**: knowledge
- **Repository**: Unknown
- **Branch**: master
- **Commit**: 634165ece5f3853496456c1e05e7e082b800ba6c
- **Author**: Jordan Partridge
- **Entries Count**: 4
- **Generator**: Knowledge CLI Documentation Generator

