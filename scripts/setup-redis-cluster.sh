#!/bin/bash

# Setup Redis Cluster for ActiveRedis Development

echo "🚀 Setting up Redis Cluster for ActiveRedis..."

# Start the cluster
echo "📦 Starting Redis Cluster containers..."
docker-compose -f docker-compose.cluster.yml up -d

# Wait for containers to be ready
echo "⏳ Waiting for Redis nodes to start..."
sleep 15

# Check cluster status
echo "🔍 Checking cluster status..."
docker exec redis-cluster redis-cli --cluster info redis-cluster:6379

# Test cluster connectivity
echo "✅ Testing cluster connectivity..."
docker exec redis-cluster redis-cli -c -h redis-cluster -p 6379 ping

# Show cluster nodes
echo "📋 Cluster nodes:"
docker exec redis-cluster redis-cli --cluster nodes redis-cluster:6379

echo ""
echo "🎉 Redis Cluster is ready!"
echo ""
echo "Cluster nodes:"
echo "  - redis-cluster:6380 (Master)"
echo ""
echo "Connection URLs:"
echo "  - localhost:6380"
echo ""
echo "Test cluster:"
echo "  docker exec redis-cluster redis-cli -c -h redis-cluster -p 6380"
echo ""
echo "Stop cluster:"
echo "  docker-compose -f docker-compose.cluster.yml down"