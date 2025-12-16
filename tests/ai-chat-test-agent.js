/**
 * AI Chat Test Agent for Dataviz AI WooCommerce Plugin
 * 
 * This script uses Playwright for browser automation and OpenAI for:
 * - Generating natural test questions
 * - Verifying chat responses
 * - Testing conversational flows
 * 
 * Run: node tests/ai-chat-test-agent.js
 */

const { chromium } = require('playwright');
const OpenAI = require('openai');
require('dotenv').config();

const openai = new OpenAI({ 
    apiKey: process.env.OPENAI_API_KEY || process.env.OPENAI_KEY 
});

// Configuration
const CONFIG = {
    pluginUrl: process.env.PLUGIN_URL || 'http://localhost:8080/wp-admin/admin.php?page=dataviz-ai-woocommerce',
    adminUser: process.env.WP_ADMIN_USER || 'admin',
    adminPass: process.env.WP_ADMIN_PASS || 'admin',
    headless: false, // Set to true for CI/CD
    timeout: 30000, // 30 seconds per test
};

// Test results
const testResults = {
    passed: [],
    failed: [],
    total: 0,
};

/**
 * Generate test questions using AI
 */
async function generateTestQuestions() {
    console.log('🤖 Generating test questions using AI...\n');
    
    const response = await openai.chat.completions.create({
        model: 'gpt-4o-mini',
        messages: [{
            role: 'system',
            content: `You are a test question generator for a WooCommerce AI analytics plugin. 
            Generate 30-50 diverse test questions that users might ask. Include:
            - Questions about orders (recent, by status, by date, totals, revenue)
            - Questions about products (list all, top sellers, by category, low stock)
            - Questions about inventory (current stock, low stock, out of stock, distribution)
            - Questions about customers (list, count, by location, top customers)
            - Questions requesting charts (pie charts, bar charts, line charts)
            - Questions about statistics (revenue, average order value, conversion rate)
            - Questions about categories and tags
            - Questions about coupons and discounts
            - Questions about refunds
            - Edge cases (empty data, invalid requests, unsupported features)
            - Feature requests for unsupported features (reviews, shipping, taxes, commission)
            - Complex queries (combining multiple data types)
            - Time-based queries (today, this week, this month, last year)
            
            Return a JSON object with a "questions" array containing all questions.`
        }, {
            role: 'user',
            content: 'Generate test questions for the WooCommerce AI plugin.'
        }],
        response_format: { type: 'json_object' }
    });

    try {
        const content = response.choices[0].message.content;
        const parsed = JSON.parse(content);
        // Handle both {questions: [...]} and direct array
        const questions = parsed.questions || parsed.questions_array || Object.values(parsed)[0] || [];
        return Array.isArray(questions) ? questions : [questions];
    } catch (e) {
        // Fallback to predefined questions
        console.log('⚠️  AI question generation failed, using predefined questions');
        return getPredefinedQuestions();
    }
}

/**
 * Predefined test questions (fallback) - Comprehensive test suite
 */
function getPredefinedQuestions() {
    return [
        // Inventory & Stock Tests
        'Show me inventory in a pie chart',
        'Show me current inventory',
        'What products are low in stock?',
        'Display inventory distribution',
        'Show me out of stock products',
        'What is my stock level?',
        'Show me inventory in a bar chart',
        
        // Products Tests
        'Show me all products',
        'What are my top selling products?',
        'List all products',
        'Show me products by category',
        'What products do I have?',
        'Show me top 10 products',
        'Display product list',
        
        // Orders Tests
        'How many orders do I have?',
        'Show me orders in a bar chart',
        'What are my recent orders?',
        'Show me all orders',
        'List my orders',
        'What is my total revenue?',
        'Show me orders by status',
        'How many orders today?',
        'What is my average order value?',
        'Show me completed orders',
        'Display order statistics',
        
        // Customers Tests
        'List all customers',
        'How many customers do I have?',
        'Show me all customers',
        'What are my top customers?',
        'Display customer list',
        
        // Charts & Visualizations
        'Show me a pie chart of order status',
        'Display a bar chart of top products',
        'Create a pie chart of inventory',
        'Show me a chart of sales',
        'Visualize my product categories',
        
        // Statistics & Analytics
        'What is my total revenue?',
        'What is my average order value?',
        'How many products do I have?',
        'Show me sales statistics',
        'What are my store metrics?',
        
        // Categories & Tags
        'Show me product categories',
        'List all categories',
        'What categories do I have?',
        'Show me product tags',
        
        // Coupons & Discounts
        'Show me all coupons',
        'List available coupons',
        'What coupons do I have?',
        
        // Refunds
        'Show me refunds',
        'List all refunds',
        'What refunds do I have?',
        
        // Feature Requests (Should trigger feature request flow)
        'Show me data for sales commission',
        'Display product reviews',
        'Show me shipping data',
        'What are my tax reports?',
        'Show me affiliate data',
        
        // Complex Queries
        'Show me products with low stock and their orders',
        'What are my top products this month?',
        'Show me orders from last week',
        'Display inventory and sales data',
        
        // Edge Cases
        'Show me data for xyz123', // Invalid query
        'What is the weather?', // Unrelated question
        'Show me everything', // Very broad query
    ];
}

/**
 * Verify if the AI response is correct using AI
 */
async function verifyResponse(question, response, hasChart = false) {
    const prompt = `You are testing a WooCommerce AI analytics plugin. 

Question asked: "${question}"
Response received: "${response}"
Chart displayed: ${hasChart ? 'Yes' : 'No'}

Evaluate if the response is:
1. Relevant to the question
2. Contains actual data (not just "I cannot help")
3. For chart requests, chart should be displayed
4. Response should be helpful and informative

Return ONLY a JSON object with:
{
    "valid": true/false,
    "reason": "brief explanation"
}`;

    try {
        const result = await openai.chat.completions.create({
            model: 'gpt-4o-mini',
            messages: [{
                role: 'user',
                content: prompt
            }],
            response_format: { type: 'json_object' }
        });

        const evaluation = JSON.parse(result.choices[0].message.content);
        return evaluation;
    } catch (e) {
        // Fallback: basic validation
        const isValid = response.length > 20 && 
                       !response.toLowerCase().includes('cannot') &&
                       !response.toLowerCase().includes("don't have access");
        return {
            valid: isValid,
            reason: isValid ? 'Response contains data' : 'Response seems invalid'
        };
    }
}

/**
 * Login to WordPress admin
 */
async function loginToWordPress(page) {
    console.log('🔐 Logging into WordPress admin...');
    
    await page.goto('http://localhost:8080/wp-login.php');
    await page.fill('#user_login', CONFIG.adminUser);
    await page.fill('#user_pass', CONFIG.adminPass);
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
    console.log('✅ Logged in successfully\n');
}

/**
 * Test a single chat question
 */
async function testChatQuestion(page, question, questionNumber) {
    console.log(`\n📝 Test ${questionNumber}: "${question}"`);
    
    try {
        // Navigate to plugin page
        await page.goto(CONFIG.pluginUrl, { waitUntil: 'networkidle' });
        
        // Wait for chat interface to load
        await page.waitForSelector('#dataviz-ai-question, .dataviz-ai-chat-input, textarea', { timeout: 15000 });
        
        // Find input field (try multiple selectors - correct ones first)
        const inputSelectors = [
            '#dataviz-ai-question',  // Correct selector
            '.dataviz-ai-chat-input',  // Class selector
            'textarea',  // Fallback to any textarea
            'input[type="text"]',
            'input[placeholder*="question"]',
            'input[placeholder*="Ask"]'
        ];
        
        let inputField = null;
        for (const selector of inputSelectors) {
            try {
                inputField = await page.$(selector);
                if (inputField) break;
            } catch (e) {}
        }
        
        if (!inputField) {
            throw new Error('Could not find chat input field');
        }
        
        // Type question
        await inputField.fill(question);
        await page.waitForTimeout(500); // Small delay
        
        // Find and click send button
        const sendSelectors = [
            '.dataviz-ai-chat-send',  // Correct class selector
            'button.dataviz-ai-chat-send',  // More specific
            'button[type="submit"]',
            '.dataviz-ai-chat-form button',  // Button in form
            'button:has-text("Send")',
            'button:has-text("Ask")'
        ];
        
        let sendButton = null;
        for (const selector of sendSelectors) {
            try {
                sendButton = await page.$(selector);
                if (sendButton) break;
            } catch (e) {}
        }
        
        if (!sendButton) {
            throw new Error('Could not find send button');
        }
        
        await sendButton.click();
        
        // Wait for response (look for AI message or chart)
        await page.waitForTimeout(2000); // Initial wait
        
        // Wait for response to appear
        const responseSelectors = [
            '.dataviz-ai-message--ai',  // Correct class for AI messages
            '.dataviz-ai-message-ai',
            '.dataviz-ai-message-content',  // Message content
            '.ai-message',
            '.chat-message-ai',
            '[class*="message"][class*="ai"]',
            '.dataviz-ai-chart-wrapper',  // Chart wrapper
            'canvas' // Chart.js canvas
        ];
        
        let responseElement = null;
        for (const selector of responseSelectors) {
            try {
                await page.waitForSelector(selector, { timeout: 20000 });
                responseElement = await page.$(selector);
                if (responseElement) break;
            } catch (e) {}
        }
        
        if (!responseElement) {
            throw new Error('No response received within timeout');
        }
        
        // Get response text - wait a bit more for streaming to complete
        await page.waitForTimeout(5000); // Wait for streaming to complete
        
        let responseText = '';
        try {
            // Try to get text from AI message
            const aiMessage = await page.$('.dataviz-ai-message--ai .dataviz-ai-message-content');
            if (aiMessage) {
                responseText = await aiMessage.textContent();
            } else {
                responseText = await responseElement.textContent();
            }
        } catch (e) {
            // Try getting from page
            responseText = await page.textContent('body');
        }
        
        // Check if chart was displayed
        const chartExists = await page.$('.dataviz-ai-chart-wrapper, canvas') !== null;
        
        // Verify response with AI
        const verification = await verifyResponse(question, responseText, chartExists);
        
        // Log result
        if (verification.valid) {
            console.log(`✅ PASSED: ${verification.reason}`);
            if (chartExists) console.log('   📊 Chart displayed');
            testResults.passed.push({ question, response: responseText.substring(0, 100) });
        } else {
            console.log(`❌ FAILED: ${verification.reason}`);
            console.log(`   Response: ${responseText.substring(0, 150)}...`);
            testResults.failed.push({ 
                question, 
                response: responseText.substring(0, 200),
                reason: verification.reason 
            });
        }
        
        testResults.total++;
        
        // Wait before next test
        await page.waitForTimeout(2000);
        
    } catch (error) {
        console.log(`❌ ERROR: ${error.message}`);
        testResults.failed.push({ 
            question, 
            error: error.message 
        });
        testResults.total++;
    }
}

/**
 * Run all tests
 */
async function runTests() {
    console.log('🚀 Starting AI Chat Test Agent\n');
    console.log('=' .repeat(60));
    
    const browser = await chromium.launch({ 
        headless: CONFIG.headless,
        slowMo: 100 // Slow down for visibility
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // Login
        await loginToWordPress(page);
        
        // Generate test questions
        const questions = await generateTestQuestions();
        console.log(`📋 Generated ${questions.length} test questions\n`);
        
        // Run tests
        for (let i = 0; i < questions.length; i++) {
            await testChatQuestion(page, questions[i], i + 1);
        }
        
    } catch (error) {
        console.error('❌ Fatal error:', error);
    } finally {
        await browser.close();
    }
    
    // Print summary
    printSummary();
}

/**
 * Print test summary
 */
function printSummary() {
    console.log('\n' + '='.repeat(60));
    console.log('📊 TEST SUMMARY');
    console.log('='.repeat(60));
    console.log(`Total Tests: ${testResults.total}`);
    console.log(`✅ Passed: ${testResults.passed.length}`);
    console.log(`❌ Failed: ${testResults.failed.length}`);
    console.log(`Success Rate: ${((testResults.passed.length / testResults.total) * 100).toFixed(1)}%`);
    
    if (testResults.failed.length > 0) {
        console.log('\n❌ Failed Tests:');
        testResults.failed.forEach((test, i) => {
            console.log(`\n${i + 1}. Question: "${test.question}"`);
            if (test.reason) console.log(`   Reason: ${test.reason}`);
            if (test.error) console.log(`   Error: ${test.error}`);
            if (test.response) console.log(`   Response: ${test.response.substring(0, 100)}...`);
        });
    }
    
    console.log('\n' + '='.repeat(60));
}

// Run tests
if (require.main === module) {
    runTests().catch(console.error);
}

module.exports = { runTests, testChatQuestion, verifyResponse };

