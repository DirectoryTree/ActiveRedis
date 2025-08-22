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
docker exec redis-cluster-1 redis-cli --cluster info redis-cluster-1:7001

# Test cluster connectivity
echo "✅ Testing cluster connectivity..."
docker exec redis-cluster-1 redis-cli -c -h redis-cluster-1 -p 7001 ping

# Show cluster nodes
echo "📋 Cluster nodes:"
docker exec redis-cluster-1 redis-cli --cluster nodes redis-cluster-1:7001

echo ""
echo "🎉 Redis Cluster is ready!"
echo ""
echo "Cluster nodes:"
echo "  - redis-cluster-1:7001 (Master)"
echo "  - redis-cluster-2:7002 (Master)" 
echo "  - redis-cluster-3:7003 (Master)"
echo "  - redis-cluster-4:7004 (Replica)"
echo "  - redis-cluster-5:7005 (Replica)"
echo "  - redis-cluster-6:7006 (Replica)"
echo ""
echo "Connection URLs:"
echo "  - localhost:7001"
echo "  - localhost:7002"
echo "  - localhost:7003"
echo "  - localhost:7004"
echo "  - localhost:7005"
echo "  - localhost:7006"
echo ""
echo "Test cluster:"
echo "  docker exec redis-cluster-1 redis-cli -c -h redis-cluster-1 -p 7001"
echo ""
echo "Stop cluster:"
echo "  docker-compose -f docker-compose.cluster.yml down"