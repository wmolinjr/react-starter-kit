#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get the directory where the script is located and navigate to project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

# Sail command with full path
SAIL="./vendor/bin/sail"

# Check if sail exists
if [ ! -f "$SAIL" ]; then
    echo -e "${RED}Error: Sail not found at $SAIL${NC}"
    echo -e "Run 'composer install' first."
    exit 1
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}   Starting Development Environment    ${NC}"
echo -e "${BLUE}========================================${NC}"

# Step 1: Start Docker containers
echo -e "\n${YELLOW}[1/5] Starting Docker containers...${NC}"
$SAIL up -d

# Wait for containers to be healthy
echo -e "${YELLOW}      Waiting for containers to be ready...${NC}"
sleep 3

# Check if PostgreSQL is ready
until $SAIL exec pgsql pg_isready -U sail -d laravel > /dev/null 2>&1; do
    echo -e "      Waiting for PostgreSQL..."
    sleep 2
done
echo -e "${GREEN}      PostgreSQL is ready!${NC}"

# Check if Redis is ready
until $SAIL exec redis redis-cli ping > /dev/null 2>&1; do
    echo -e "      Waiting for Redis..."
    sleep 1
done
echo -e "${GREEN}      Redis is ready!${NC}"

echo -e "${GREEN}[1/5] Docker containers are running!${NC}"

# Step 2: Start Vite dev server
echo -e "\n${YELLOW}[2/5] Starting Vite dev server...${NC}"
$SAIL npm run dev &
VITE_PID=$!
echo -e "${GREEN}[2/5] Vite started (PID: $VITE_PID)${NC}"

# Step 3: Start Queue Worker (Horizon or queue:work)
echo -e "\n${YELLOW}[3/5] Starting Queue Worker...${NC}"
if [ "$1" == "--horizon" ] || [ "$1" == "--full" ]; then
    echo -e "      Using Laravel Horizon (dashboard at http://app.test/horizon)"
    $SAIL artisan horizon &
    QUEUE_PID=$!
    echo -e "${GREEN}[3/5] Horizon started (PID: $QUEUE_PID)${NC}"
else
    echo -e "      Processing queues: high, default, federation, media"
    echo -e "      (use --horizon for dashboard)"
    $SAIL artisan queue:work redis --queue=high,default,federation,media --tries=3 --timeout=300 &
    QUEUE_PID=$!
    echo -e "${GREEN}[3/5] Queue Worker started (PID: $QUEUE_PID)${NC}"
fi

# Step 4: Start Scheduler (optional)
if [ "$1" == "--with-scheduler" ] || [ "$1" == "--full" ]; then
    echo -e "\n${YELLOW}[4/5] Starting Scheduler...${NC}"
    $SAIL artisan schedule:work &
    SCHEDULE_PID=$!
    echo -e "${GREEN}[4/5] Scheduler started (PID: $SCHEDULE_PID)${NC}"
else
    echo -e "\n${YELLOW}[4/5] Scheduler skipped (use --with-scheduler to enable)${NC}"
fi

# Step 5: Start Stripe webhook listener (optional)
if [ "$1" == "--with-stripe" ] || [ "$1" == "--full" ]; then
    echo -e "\n${YELLOW}[5/5] Starting Stripe webhook listener...${NC}"
    if command -v stripe &> /dev/null; then
        stripe listen --forward-to http://app.test/stripe/webhook &
        STRIPE_PID=$!
        echo -e "${GREEN}[5/5] Stripe listener started (PID: $STRIPE_PID)${NC}"
    else
        echo -e "${YELLOW}[5/5] Stripe CLI not installed. Skipping...${NC}"
        echo -e "      Install: https://stripe.com/docs/stripe-cli"
    fi
else
    echo -e "\n${YELLOW}[5/5] Stripe skipped (use --with-stripe or --full to enable)${NC}"
fi

echo -e "\n${BLUE}========================================${NC}"
echo -e "${GREEN}   Development Environment Ready!      ${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e ""
echo -e "  App:       ${GREEN}http://app.test${NC}"
echo -e "  Tenant 1:  ${GREEN}http://tenant1.test${NC}"
echo -e "  Mailpit:   ${GREEN}http://localhost:8025${NC}"
echo -e "  Telescope: ${GREEN}http://app.test/telescope${NC}"
if [ "$1" == "--horizon" ] || [ "$1" == "--full" ]; then
    echo -e "  Horizon:   ${GREEN}http://app.test/horizon${NC}"
fi
echo -e ""
echo -e "  Press ${YELLOW}Ctrl+C${NC} to stop all services"
echo -e ""

# Wait for any process to exit
wait
