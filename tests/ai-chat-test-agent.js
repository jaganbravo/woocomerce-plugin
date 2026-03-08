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
const PDFDocument = require('pdfkit');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const expectedIntentsPath = path.join(__dirname, 'expected-intents.json');
let EXPECTED_INTENTS = {};
try {
    if (fs.existsSync(expectedIntentsPath)) {
        EXPECTED_INTENTS = JSON.parse(fs.readFileSync(expectedIntentsPath, 'utf8')) || {};
    }
} catch (e) {
    console.log(`[WARN] Failed to load expected intents: ${e.message}`);
}

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
    allTests: [], // Store all tests with full Q&A
    performance: {
        totalResponseTime: 0,
        totalStreamingTime: 0,
        averageResponseTime: 0,
        averageStreamingTime: 0,
        fastestResponse: Infinity,
        slowestResponse: 0,
    },
};

/**
 * Get path to saved questions file
 */
function getQuestionsFilePath() {
    return path.join(__dirname, 'saved-questions.json');
}

/**
 * Save questions to file
 */
function saveQuestions(questions) {
    const filePath = getQuestionsFilePath();
    const data = {
        questions: questions,
        generatedAt: new Date().toISOString(),
        count: questions.length
    };
    fs.writeFileSync(filePath, JSON.stringify(data, null, 2));
    console.log(`[SAVE] Saved ${questions.length} questions to ${filePath}`);
}

/**
 * Load saved questions from file
 */
function loadSavedQuestions() {
    const filePath = getQuestionsFilePath();
    if (fs.existsSync(filePath)) {
        try {
            const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
            const questions = data.questions || [];
            if (questions.length > 0) {
                console.log(`[LOAD] Loaded ${questions.length} saved questions from ${filePath}`);
                console.log(`[INFO] Questions generated at: ${data.generatedAt || 'unknown'}`);
                return questions;
            }
        } catch (e) {
            console.log(`[WARN]  Failed to load saved questions: ${e.message}`);
        }
    }
    return null;
}

/**
 * Generate test questions using AI
 */
async function generateTestQuestions(useSaved = false) {
    // Try to load saved questions first if requested
    if (useSaved) {
        const savedQuestions = loadSavedQuestions();
        if (savedQuestions) {
            return savedQuestions;
        }
        console.log('[INFO] No saved questions found, generating new ones...\n');
    }
    
    console.log('[AI] Generating test questions using AI...\n');
    
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
        const questionArray = Array.isArray(questions) ? questions : [questions];
        
        // Save generated questions
        saveQuestions(questionArray);
        
        return questionArray;
    } catch (e) {
        // Fallback to predefined questions
        console.log('[WARN]  AI question generation failed, using predefined questions');
        const predefined = getPredefinedQuestions();
        // Save predefined questions for future use
        saveQuestions(predefined);
        return predefined;
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
 * Validate response contains specific data types based on question
 */
function isFeatureRequestResponse(response) {
    const lr = response.toLowerCase();
    return (
        /\b(feature request|request this feature|submit a feature request|would you like to request)\b/i.test(response) ||
        /\b(not currently supported|not yet available|not currently available)\b/i.test(response) ||
        /\b(wasn't able to understand this request)\b/i.test(response) ||
        (lr.includes('would you like') && lr.includes('request')) ||
        (lr.includes('combine') && lr.includes('not') && lr.includes('support')) ||
        /\bcross.entity\b/i.test(response) ||
        /\bnot .* supported .* data type\b/i.test(response)
    );
}

function isZeroDataResponse(response) {
    const lr = response.toLowerCase();
    return (
        /\bno (orders|products|customers|coupons|refunds|records|data)\b/i.test(response) ||
        /\bno coupons were used\b/i.test(response) ||
        /\bthere are (no|0) (orders|products|customers|coupons|refunds)\b/i.test(response) ||
        /\b0 (customers|orders|products|refunds)\b/i.test(response) ||
        /\bcouldn'?t find\b/i.test(response) ||
        /\bno records matching\b/i.test(response) ||
        /\bno .+ found in the specified period\b/i.test(response) ||
        /\bno .+ (were|was) (found|used|placed|issued)\b/i.test(response) ||
        /\bthere are 0 refunds\b/i.test(response) ||
        lr.includes('no orders found') ||
        lr.includes('no coupons were used') ||
        lr.includes('no refunds found') ||
        lr.includes('no customers found') ||
        lr.includes('no records matching')
    );
}

/**
 * Check if the response indicates an internal error (not a data error, but an actual system error).
 * These should NOT be treated as valid — they indicate bugs to be fixed.
 */
function isErrorResponse(response) {
    const lr = response.toLowerCase();
    return (
        /\b(error in retrieving|error occurred|unexpected error|unable to process)\b/i.test(response) ||
        lr.includes('an error occurred') ||
        lr.includes('error retrieving') ||
        lr.includes('unable to process data query')
    );
}

function isInformationalQuestion(question) {
    const lq = question.toLowerCase();
    return (
        /\b(what happens if|are there any empty|what should i|how do i)\b/i.test(question) &&
        !/\b(show me|list|display|get|how many)\b/i.test(question)
    );
}

function validateResponseData(question, response) {
    const lowerQuestion = question.toLowerCase();
    const lowerResponse = response.toLowerCase();
    const issues = [];
    const validations = [];

    const featureRequest = isFeatureRequestResponse(response);
    const informational = isInformationalQuestion(question);
    const zeroData = isZeroDataResponse(response);

    if (featureRequest) {
        validations.push('Feature request response (data validation skipped)');
        return { issues, validations, hasSpecificData: true };
    }
    if (informational) {
        validations.push('Informational/meta question (data validation skipped)');
        return { issues, validations, hasSpecificData: true };
    }
    if (zeroData) {
        validations.push('Zero-data response (legitimate empty result)');
        return { issues, validations, hasSpecificData: true };
    }

    // Check for numbers in statistics/count queries
    if (/\b(how many|count|total|number of|quantity|amount)\b/i.test(question)) {
        const hasNumber = /\d+/.test(response);
        if (!hasNumber) {
            issues.push('Missing numeric value for count/statistics query');
        } else {
            validations.push('Contains numeric data');
        }
    }

    // Check for currency/revenue in revenue queries
    if (/\b(revenue|sales|total sales|income|profit|price|cost)\b/i.test(question)) {
        const hasCurrency = /[$£€¥]|\d+\.\d{2}|\d+,\d{3}/.test(response);
        if (!hasCurrency && !/\d+/.test(response)) {
            issues.push('Missing currency or numeric value for revenue query');
        } else {
            validations.push('Contains financial data');
        }
    }

    // Check for status breakdown in status queries
    if (/\b(status|statuses)\b/i.test(question)) {
        const statusKeywords = ['completed', 'pending', 'processing', 'cancelled', 'refunded', 'failed', 'on-hold'];
        const hasStatus = statusKeywords.some(status => lowerResponse.includes(status));
        if (!hasStatus) {
            issues.push('Missing status breakdown for status query');
        } else {
            validations.push('Contains status information');
        }
    }

    // Check for product names in product queries
    if (/\b(product|products|item|items)\b/i.test(question) && !/\b(category|categories|tag|tags)\b/i.test(question)) {
        // Look for product-like patterns (capitalized words, SKUs, etc.)
        const hasProductData = /[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*|\bSKU\b|\bproduct\b/i.test(response);
        if (!hasProductData && !/\d+/.test(response)) {
            issues.push('Missing product information for product query');
        } else {
            validations.push('Contains product data');
        }
    }

    // Check for customer data in customer queries
    if (/\b(customer|customers|client|clients)\b/i.test(question)) {
        const hasEmail = /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i.test(response);
        const hasCustomerKeywords = /\b(customer|customers|client|clients|email|name)\b/i.test(response);
        const hasList = (response.match(/\n\s*\d+[\.\)]\s+/g) || []).length >= 2 || (response.match(/[•\-\*]\s+/g) || []).length >= 2;
        const hasMoneyOrTotals = /[$£€¥]|\d+\.\d{2}|\d+,\d{3}|\b(total spent|spend|spent)\b/i.test(response);

        const isTopCustomersQuery = /\btop\b/i.test(question) && /\bcustomers?\b/i.test(question);
        if (isTopCustomersQuery) {
            // For top customers, numbers alone are NOT enough; require identifiable customer entries.
            if (!(hasEmail || (hasCustomerKeywords && hasList)) || !hasMoneyOrTotals) {
                issues.push('Missing top-customer list (names/emails) and spend totals for top customers query');
            } else {
                validations.push('Contains top customer list with spend data');
            }
        } else {
            const hasCustomerData = hasEmail || hasCustomerKeywords || hasList;
            if (!hasCustomerData) {
                issues.push('Missing customer information for customer query');
            } else {
                validations.push('Contains customer data');
            }
        }
    }

    // Check for date/time in time-based queries
    if (/\b(today|yesterday|this week|this month|last week|last month|date|time)\b/i.test(question)) {
        const hasDate = /\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}|(january|february|march|april|may|june|july|august|september|october|november|december)/i.test(response);
        if (!hasDate && !/\d+/.test(response)) {
            // Date not required if response has numbers (could be counts)
            validations.push('Contains time-based data');
        } else if (hasDate) {
            validations.push('Contains date information');
        }
    }

    // Check for "all/every/entire" queries - must return multiple items
    const wantsAll = /\b(all|every|entire|complete|full|list all|show all|display all)\b/i.test(question);
    if (wantsAll) {
        // Count items in response (look for numbered lists, bullet points, or multiple entries)
        const numberedListMatches = response.match(/\d+[\.\)]\s+[A-Z]/g) || [];
        const bulletMatches = response.match(/[•\-\*]\s+[A-Z]/g) || [];
        const lineBreaks = (response.match(/\n/g) || []).length;
        
        // Look for patterns like "1. Product Name", "Product 1", "Product A", etc.
        const itemPatterns = [
            /\d+[\.\)]\s+[A-Z][a-z]+/g,  // "1. Product"
            /[•\-\*]\s+[A-Z][a-z]+/g,    // "• Product"
            /^[A-Z][a-z]+.*$/gm,         // Lines starting with capital (potential list items)
        ];
        
        let itemCount = 0;
        itemPatterns.forEach(pattern => {
            const matches = response.match(pattern);
            if (matches) {
                itemCount = Math.max(itemCount, matches.length);
            }
        });
        
        // Also check for explicit counts in response
        const countMatches = response.match(/\b(\d+)\s+(product|order|customer|item)/gi);
        if (countMatches) {
            const explicitCount = parseInt(countMatches[0].match(/\d+/)[0]);
            itemCount = Math.max(itemCount, explicitCount);
        }
        
        // Check if response indicates completeness ("all X products", "complete list", etc.)
        const indicatesComplete = /\b(all|every|entire|complete|full list|all \d+|total of \d+)\b/i.test(response);
        
        // If user wants "all" but response shows only 1 item and doesn't indicate completeness
        if (itemCount <= 1 && !indicatesComplete && lineBreaks < 3) {
            issues.push('User requested "all" but response appears to show only 1 item or incomplete list');
        } else if (itemCount > 1 || indicatesComplete) {
            validations.push(`Contains ${itemCount > 1 ? itemCount + ' items' : 'complete list'} for "all" query`);
        }
    }

    return {
        issues,
        validations,
        hasSpecificData: validations.length > 0 || issues.length === 0
    };
}

function deepPartialMatch(actual, expected) {
    if (expected === null || expected === undefined) return true;
    if (typeof expected !== 'object' || expected === null) {
        return actual === expected;
    }
    if (Array.isArray(expected)) {
        if (!Array.isArray(actual)) return false;
        // Expected array acts as “must include these items”
        return expected.every(item => actual.includes(item));
    }
    if (typeof actual !== 'object' || actual === null) return false;
    return Object.keys(expected).every(key => deepPartialMatch(actual[key], expected[key]));
}

async function fetchValidatedIntent(page, question) {
    // Extract localized globals from plugin admin page
    const { ajaxUrl, nonce } = await page.evaluate(() => {
        return {
            ajaxUrl: (window.DatavizAIAdmin && window.DatavizAIAdmin.ajaxUrl) || '',
            nonce: (window.DatavizAIAdmin && window.DatavizAIAdmin.nonce) || ''
        };
    });

    if (!ajaxUrl || !nonce) {
        return { ok: false, error: 'Missing ajaxUrl/nonce from page' };
    }

    const resp = await page.request.post(ajaxUrl, {
        form: {
            action: 'dataviz_ai_debug_intent',
            nonce,
            question
        },
        timeout: CONFIG.timeout
    });

    const json = await resp.json().catch(() => null);
    if (!json) return { ok: false, error: 'Non-JSON response from intent endpoint' };
    if (!json.success) return { ok: false, error: json.data && json.data.message ? json.data.message : 'Intent endpoint error', data: json.data };
    return { ok: true, intent: json.data.validated_intent };
}

/**
 * Verify if the AI response is correct using AI + specific validation
 */
async function verifyResponse(question, response, hasChart = false, performanceMetrics = {}) {
    // First, do specific data validation
    const dataValidation = validateResponseData(question, response);
    
    // Build enhanced prompt with validation results
    const validationInfo = dataValidation.validations.length > 0 
        ? `\nData Validation: ${dataValidation.validations.join(', ')}`
        : '';
    const issuesInfo = dataValidation.issues.length > 0
        ? `\nValidation Issues: ${dataValidation.issues.join(', ')}`
        : '';
    const performanceInfo = performanceMetrics.responseTime 
        ? `\nResponse Time: ${performanceMetrics.responseTime}ms, Streaming Time: ${performanceMetrics.streamingTime}ms`
        : '';

    const prompt = `You are testing a WooCommerce AI analytics plugin. 

Question asked: "${question}"
Response received: "${response}"
Chart displayed: ${hasChart ? 'Yes' : 'No'}${validationInfo}${issuesInfo}${performanceInfo}

Evaluate if the response is:
1. Relevant to the question (does it address what was asked?)
2. Contains actual data or useful information (not just greetings, errors, or "I cannot help")
3. For chart requests, chart should be displayed (if question asks for chart)
4. Response should be helpful and informative
5. Response format should match the question type (statistics should have numbers, lists should have items, etc.)

IMPORTANT: 
- A response that contains actual WooCommerce data (orders, products, customers, statistics, etc.) is VALID
- A response that is a greeting like "Hello! How can I assist you?" without data is INVALID
- A response that provides data but doesn't have a chart (when chart was requested) should still be VALID if it contains the data
- For count/statistics queries, response MUST contain numeric values
- For revenue queries, response should contain currency or numbers
- **CRITICAL: If user asks for "all" items (e.g., "list all products", "show all orders") and response shows only 1 item, mark as INVALID** - "all" means multiple items, not just one
- If user asks for "all" and response shows multiple items or explicitly states completeness (e.g., "all 50 products"), mark as VALID
- A response that correctly identifies a feature as unsupported and offers to submit a feature request IS VALID (e.g., comparison queries, conversion rate with traffic data, social media referrals, cross-entity combination queries)
- A response that explains what data is or isn't available IS VALID for informational/meta questions (e.g., "What happens if I request unsupported features?", "Are there empty data sets?")
- A response that explicitly states zero/no results (e.g., "No coupons were used", "0 customers have placed orders", "No refunds found", "I couldn't find a tag named X", "No records matching your query") IS VALID — the system correctly queried but found no matching data. This is a legitimate deterministic answer.
- Only mark as INVALID if the response is clearly irrelevant, contains no data, is just a greeting, OR violates the "all" requirement above (EXCEPT when the response explicitly says no matching items were found — that's valid for "all" queries too)
${dataValidation.issues.length > 0 ? `\n⚠️ VALIDATION ISSUES DETECTED: ${dataValidation.issues.join(', ')}. These should cause the test to FAIL if they indicate incomplete data (especially "all" queries showing only 1 item).` : ''}

Return ONLY a JSON object with:
{
    "valid": true/false,
    "reason": "brief explanation",
    "dataQuality": "excellent|good|fair|poor",
    "suggestions": "optional improvement suggestions"
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
        
        // Feature-request, informational, and legitimate zero-data responses bypass strict data validation.
        const skipHardFail = isFeatureRequestResponse(response) || isInformationalQuestion(question) || isZeroDataResponse(response);

        // Treat validation issues as hard failures (prevents irrelevant responses from passing).
        if (dataValidation.issues.length > 0 && !skipHardFail) {
            if (evaluation.valid) {
                evaluation.reason += ` (FAIL: ${dataValidation.issues.join(', ')})`;
            }
            evaluation.valid = false;
            evaluation.dataQuality = 'poor';
        }
        
        return {
            ...evaluation,
            dataValidation: dataValidation,
            performanceMetrics: performanceMetrics
        };
    } catch (e) {
        // Enhanced fallback validation
        const hasDataKeywords = /order|product|customer|revenue|sale|inventory|stock|statistic|total|count|refund|coupon|category|tag/i.test(response);
        const hasNumbers = /\d+/.test(response);
        const isNotGreeting = !/^hello[!.]?\s*(how can i assist you|how may i help)/i.test(response);
        const featureReq = isFeatureRequestResponse(response);
        
        if (featureReq) {
            return {
                valid: true,
                reason: 'Feature request response detected (fallback)',
                dataValidation: dataValidation,
                performanceMetrics: performanceMetrics,
                dataQuality: 'good'
            };
        }

        const zeroDataResp = isZeroDataResponse(response);
        if (zeroDataResp) {
            return {
                valid: true,
                reason: 'Zero-data / no-results response detected (fallback)',
                dataValidation: dataValidation,
                performanceMetrics: performanceMetrics,
                dataQuality: 'fair'
            };
        }

        // Check for "all" queries - must have multiple items or indicate completeness
        const wantsAll = /\b(all|every|entire|complete|full|list all|show all|display all)\b/i.test(question);
        let allQueryValid = true;
        if (wantsAll) {
            // Count items in response
            const itemCount = (response.match(/\d+[\.\)]\s+[A-Z]/g) || []).length + 
                            (response.match(/[•\-\*]\s+[A-Z]/g) || []).length;
            const indicatesComplete = /\b(all|every|entire|complete|full list|all \d+|total of \d+)\b/i.test(response);
            const explicitCount = response.match(/\b(\d+)\s+(product|order|customer|item)/gi);
            
            // If "all" requested but only 1 item and no indication of completeness
            if (itemCount <= 1 && !indicatesComplete && !explicitCount && response.split('\n').length < 3) {
                allQueryValid = false;
            }
        }
        
        const isValid = response.length > 20 && 
                       hasDataKeywords &&
                       isNotGreeting &&
                       allQueryValid &&
                       !response.toLowerCase().includes('cannot') &&
                       !response.toLowerCase().includes("don't have access");
        
        let reason = isValid 
            ? `Response contains data (${hasNumbers ? 'with numbers' : 'without numbers'})` 
            : 'Response seems invalid';
        
        if (wantsAll && !allQueryValid) {
            reason = 'User requested "all" but response shows only 1 item or incomplete list';
        }
        
        return {
            valid: isValid,
            reason: reason,
            dataValidation: dataValidation,
            performanceMetrics: performanceMetrics,
            dataQuality: isValid && hasNumbers && allQueryValid ? 'good' : isValid ? 'fair' : 'poor'
        };
    }
}

/**
 * Login to WordPress admin
 */
async function loginToWordPress(page) {
    console.log('[LOGIN] Logging into WordPress admin...');
    
    try {
        const loginUrl = process.env.WP_LOGIN_URL || new URL('/wp-login.php', CONFIG.pluginUrl).toString();
        
        // WordPress pages often keep background requests open, so `networkidle` can hang.
        // Use `domcontentloaded` and a larger timeout for reliability.
        await page.goto(loginUrl, { 
            waitUntil: 'domcontentloaded',
            timeout: CONFIG.timeout || 30000
        });
    } catch (error) {
        if (error.message.includes('ERR_CONNECTION_REFUSED') || 
            error.message.includes('net::ERR') ||
            error.message.includes('Navigation failed')) {
            console.error('\n❌ ERROR: Cannot connect to WordPress at http://localhost:8080');
            console.error('\n📋 Docker WordPress is not running. Please start it first:');
            console.error('   1. Navigate to docker directory: cd docker');
            console.error('   2. Start containers: docker compose up -d');
            console.error('   3. Wait for WordPress to be ready (check: http://localhost:8080)');
            console.error('   4. Run tests again: npm run test:static');
            console.error('\n💡 Quick check: docker compose ps (should show wp_app container running)\n');
            throw new Error('WordPress not accessible. Please start Docker containers first.');
        }
        throw error;
    }
    
    await page.fill('#user_login', CONFIG.adminUser);
    await page.fill('#user_pass', CONFIG.adminPass);
    await page.click('#wp-submit');
    
    // Wait for dashboard
    await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
    console.log('[SUCCESS] Logged in successfully\n');
}

/**
 * Test a single chat question
 */
async function testChatQuestion(page, question, questionNumber) {
    console.log('\n[TEST] Test ' + questionNumber + ': "' + question + '"');
    
    // Performance tracking - initialize before try block so it's always available
    const performanceMetrics = {
        questionStartTime: Date.now(),
        streamingStartTime: null,
        streamingEndTime: null,
        responseTime: null,
        streamingTime: null,
    };
    
    try {
        // Navigate to plugin page
        await page.goto(CONFIG.pluginUrl, { waitUntil: 'networkidle' });
        
        // Wait for chat interface to load
        await page.waitForSelector('#dataviz-ai-question, .dataviz-ai-chat-input, textarea', { timeout: 15000 });

        // Intent-layer golden check (partial match) when an expectation exists
        if (EXPECTED_INTENTS && EXPECTED_INTENTS[question]) {
            const expected = EXPECTED_INTENTS[question];
            const intentResp = await fetchValidatedIntent(page, question);
            if (!intentResp.ok) {
                throw new Error(`Intent check failed: ${intentResp.error}`);
            }
            const ok = deepPartialMatch(intentResp.intent, expected);
            if (!ok) {
                throw new Error(`Intent mismatch. Expected partial=${JSON.stringify(expected)} Actual=${JSON.stringify(intentResp.intent)}`);
            }
            console.log('   [OK] Intent matched expected (partial)');
        }
        
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
        
        // Count existing AI messages before sending (to identify the new one)
        const aiMessagesBefore = await page.$$('.dataviz-ai-message--ai');
        const messageCountBefore = aiMessagesBefore.length;
        
        await sendButton.click();
        
        // Wait for send button to be disabled (streaming started)
        await page.waitForTimeout(1000);
        
        // Wait for send button to be re-enabled (streaming completed)
        // This is a reliable signal that the stream is done
        try {
            await page.waitForFunction(
                () => {
                    const sendBtn = document.querySelector('.dataviz-ai-chat-send');
                    return sendBtn && !sendBtn.disabled && sendBtn.offsetParent !== null;
                },
                { timeout: 30000 }
            );
            console.log('   [OK] Streaming completed (send button re-enabled)');
        } catch (e) {
            console.log('   [WARN]  Send button not re-enabled within timeout, continuing...');
        }
        
        // Wait for response (look for AI message or chart)
        await page.waitForTimeout(2000); // Additional wait for UI updates
        
        // Wait for a NEW AI message to appear (count should increase)
        let newMessageFound = false;
        for (let i = 0; i < 20; i++) {
            await page.waitForTimeout(500);
            const aiMessagesAfter = await page.$$('.dataviz-ai-message--ai');
            if (aiMessagesAfter.length > messageCountBefore) {
                newMessageFound = true;
                break;
            }
        }
        
        if (!newMessageFound) {
            // Fallback: wait for any AI message
            await page.waitForSelector('.dataviz-ai-message--ai', { timeout: 20000 });
        }
        
        // Get ALL AI messages and select the LAST one (most recent)
        const allAiMessages = await page.$$('.dataviz-ai-message--ai');
        if (allAiMessages.length === 0) {
            throw new Error('No AI messages found');
        }
        
        // The last message is the most recent one
        const latestAiMessage = allAiMessages[allAiMessages.length - 1];
        
        // Wait for streaming to complete - check multiple times until response is stable
        let responseText = '';
        let previousResponse = '';
        let stableCount = 0;
        const maxWaitTime = 30000; // 30 seconds max
        const checkInterval = 1000; // Check every second
        const stableThreshold = 3; // Response must be stable for 3 checks
        
        for (let waitTime = 0; waitTime < maxWaitTime; waitTime += checkInterval) {
            await page.waitForTimeout(checkInterval);
            
            try {
                // Get ALL AI messages and select the LAST one (most recent)
                const allAiMessages = await page.$$('.dataviz-ai-message--ai');
                if (allAiMessages.length > 0) {
                    const latestMessage = allAiMessages[allAiMessages.length - 1];
                    // Try to get text from the latest message's content
                    const messageContent = await latestMessage.$('.dataviz-ai-message-content');
                    if (messageContent) {
                        responseText = await messageContent.textContent();
                    } else {
                        // Fallback: get text from the message container itself
                        responseText = await latestMessage.textContent();
                    }
                } else {
                    // Fallback: try getting from any AI message (shouldn't happen)
                    // Get the latest AI message (most recent)
                    const allAiMessages = await page.$$('.dataviz-ai-message--ai');
                    if (allAiMessages.length > 0) {
                        const latestMessage = allAiMessages[allAiMessages.length - 1];
                        const messageContent = await latestMessage.$('.dataviz-ai-message-content');
                        if (messageContent) {
                            responseText = await messageContent.textContent();
                        } else {
                            responseText = await latestMessage.textContent();
                        }
                    }
                }
                
                responseText = responseText ? responseText.trim() : '';
                
                // Check if response is stable (not changing)
                if (responseText === previousResponse && responseText.length > 0) {
                    stableCount++;
                    if (stableCount >= stableThreshold) {
                        // Response is stable, break
                        break;
                    }
                } else {
                    stableCount = 0;
                    previousResponse = responseText;
                }
                
                // If response contains data keywords and is not just a greeting, it's likely complete
                const hasDataKeywords = /order|product|customer|revenue|sale|inventory|stock|statistic/i.test(responseText);
                const isNotJustGreeting = !/^hello[!.]?\s*(how can i assist you|how may i help)/i.test(responseText);
                
                if (hasDataKeywords && isNotJustGreeting && responseText.length > 50) {
                    // Response looks complete, break early
                    break;
                }
            } catch (e) {
                // Continue waiting
            }
        }
        
        // Final extraction - get the latest AI message
        if (!responseText || responseText.length < 10) {
            try {
                // Get ALL AI messages and select the LAST one (most recent)
                const allAiMessages = await page.$$('.dataviz-ai-message--ai');
                if (allAiMessages.length > 0) {
                    const latestMessage = allAiMessages[allAiMessages.length - 1];
                    const messageContent = await latestMessage.$('.dataviz-ai-message-content');
                    if (messageContent) {
                        responseText = await messageContent.textContent();
                    } else {
                        responseText = await latestMessage.textContent();
                    }
                }
                responseText = responseText ? responseText.trim() : '';
            } catch (e) {
                responseText = '';
            }
        }
        
        // Debug: log the actual response for troubleshooting
        if (responseText.length < 50) {
            console.log('   [WARN]  Warning: Response seems short (' + responseText.length + ' chars): "' + responseText + '"');
        }
        
        // Additional check: if response is just a greeting, wait longer for tools to execute
        if (/^hello[!.]?\s*(how can i assist you|how may i help)/i.test(responseText) && responseText.length < 100) {
            console.log('   [WARN]  Detected greeting, waiting for tools to execute and response to update...');
            
            // Wait for response to change (tools might be executing)
            for (let i = 0; i < 10; i++) {
                await page.waitForTimeout(2000); // Wait 2 seconds
                
                try {
                    // Get the latest AI message (most recent)
                    const allAiMessages = await page.$$('.dataviz-ai-message--ai');
                    if (allAiMessages.length > 0) {
                        const latestMessage = allAiMessages[allAiMessages.length - 1];
                        const messageContent = await latestMessage.$('.dataviz-ai-message-content');
                        const newResponse = messageContent ? await messageContent.textContent() : await latestMessage.textContent();
                        const newResponseTrimmed = newResponse ? newResponse.trim() : '';
                        
                        // Check if response has changed and contains data
                        if (newResponseTrimmed.length > responseText.length) {
                            responseText = newResponseTrimmed;
                            
                            // If new response has data keywords, it's likely the real response
                            const hasDataKeywords = /order|product|customer|revenue|sale|inventory|stock|statistic|total|count/i.test(responseText);
                            if (hasDataKeywords && !/^hello[!.]?\s*(how can i assist you|how may i help)/i.test(responseText)) {
                                console.log('   [OK] Response updated with data after ' + (i + 1) * 2 + ' seconds');
                                break;
                            }
                        }
                    }
                } catch (e) {}
            }
            
            // Final check: if still just greeting, log for debugging
            if (/^hello[!.]?\s*(how can i assist you|how may i help)/i.test(responseText) && responseText.length < 100) {
                console.log('   [WARN]  Response still appears to be just a greeting after waiting');
            }
        }
        
        // Calculate total response time
        performanceMetrics.responseTime = Date.now() - performanceMetrics.questionStartTime;
        
        // Check if chart was displayed
        const chartExists = await page.$('.dataviz-ai-chart-wrapper, canvas') !== null;
        
        // Verify response with AI (including performance metrics)
        const verification = await verifyResponse(question, responseText, chartExists, performanceMetrics);
        
        // Update performance metrics
        testResults.performance.totalResponseTime += performanceMetrics.responseTime;
        if (performanceMetrics.streamingTime) {
            testResults.performance.totalStreamingTime += performanceMetrics.streamingTime;
        }
        testResults.performance.fastestResponse = Math.min(testResults.performance.fastestResponse, performanceMetrics.responseTime);
        testResults.performance.slowestResponse = Math.max(testResults.performance.slowestResponse, performanceMetrics.responseTime);
        
        // Store full test result
        const testResult = {
            question,
            response: responseText,
            passed: verification.valid,
            reason: verification.reason,
            chartDisplayed: chartExists,
            dataQuality: verification.dataQuality || 'unknown',
            performanceMetrics: performanceMetrics,
            dataValidation: verification.dataValidation,
            timestamp: new Date().toISOString()
        };
        
        testResults.allTests.push(testResult);
        
        // Log result with performance info
        if (verification.valid) {
            console.log(`[SUCCESS] PASSED: ${verification.reason}`);
            console.log(`   [PERF] Response: ${performanceMetrics.responseTime}ms, Streaming: ${performanceMetrics.streamingTime || 'N/A'}ms`);
            console.log(`   [DATA] Quality: ${verification.dataQuality || 'unknown'}`);
            if (chartExists) console.log('   [CHART] Chart displayed');
            if (verification.dataValidation && verification.dataValidation.validations.length > 0) {
                console.log(`   [VALID] ${verification.dataValidation.validations.join(', ')}`);
            }
            testResults.passed.push({ 
                question, 
                response: responseText.substring(0, 100),
                responseTime: performanceMetrics.responseTime,
                dataQuality: verification.dataQuality
            });
        } else {
            console.log(`[FAIL] FAILED: ${verification.reason}`);
            console.log(`   [PERF] Response: ${performanceMetrics.responseTime}ms`);
            console.log(`   [DATA] Quality: ${verification.dataQuality || 'unknown'}`);
            if (verification.dataValidation && verification.dataValidation.issues.length > 0) {
                console.log(`   [ISSUES] ${verification.dataValidation.issues.join(', ')}`);
            }
            console.log('   Response: ' + responseText.substring(0, 150) + '...');
            testResults.failed.push({ 
                question, 
                response: responseText.substring(0, 200),
                reason: verification.reason,
                responseTime: performanceMetrics.responseTime,
                dataQuality: verification.dataQuality,
                issues: verification.dataValidation?.issues || []
            });
        }
        
        testResults.total++;
        
        // Wait before next test
        await page.waitForTimeout(2000);
        
    } catch (error) {
        // Calculate response time (performanceMetrics is initialized before try block)
        performanceMetrics.responseTime = Date.now() - performanceMetrics.questionStartTime;
        console.log('[FAIL] ERROR: ' + error.message + '');
        const testResult = {
            question,
            response: '',
            passed: false,
            reason: 'Error: ' + error.message,
            chartDisplayed: false,
            performanceMetrics: performanceMetrics,
            dataQuality: 'poor',
            timestamp: new Date().toISOString()
        };
        testResults.allTests.push(testResult);
        testResults.failed.push({ 
            question, 
            error: error.message,
            responseTime: performanceMetrics.responseTime
        });
        testResults.total++;
    }
}

/**
 * Run all tests
 */
async function runTests(useStatic = false) {
    const testMode = useStatic ? 'STATIC' : 'AI-GENERATED';
    console.log(`[START] Starting AI Chat Test Agent (${testMode} MODE)\n`);
    console.log('=' .repeat(60));
    
    const browser = await chromium.launch({ 
        headless: CONFIG.headless || process.env.HEADLESS === 'true',
        slowMo: 100 // Slow down for visibility
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // Login
        await loginToWordPress(page);
        
        // Generate or load test questions
        let questions;
        if (useStatic) {
            // Use predefined questions for static testing
            questions = getPredefinedQuestions();
            console.log('[STATIC] Using predefined test questions\n');
        } else {
            // Try to use saved questions, generate new ones if not available
            questions = await generateTestQuestions(true); // useSaved = true
        }
        
        console.log(`[LIST] Running ${questions.length} test questions\n`);
        
        // Run tests
        for (let i = 0; i < questions.length; i++) {
            await testChatQuestion(page, questions[i], i + 1);
        }
        
    } catch (error) {
        console.error('[FAIL] Fatal error:', error);
    } finally {
        await browser.close();
    }
    
    // Print summary
    printSummary();
    
    // Generate PDF report
    await generatePDFReport();
}

/**
 * Print test summary
 */
function printSummary() {
    // Calculate average performance metrics
    if (testResults.total > 0) {
        testResults.performance.averageResponseTime = Math.round(testResults.performance.totalResponseTime / testResults.total);
        const streamingTests = testResults.allTests.filter(t => t.performanceMetrics && t.performanceMetrics.streamingTime);
        if (streamingTests.length > 0) {
            testResults.performance.averageStreamingTime = Math.round(
                streamingTests.reduce((sum, t) => sum + t.performanceMetrics.streamingTime, 0) / streamingTests.length
            );
        }
    }
    
    console.log('\n' + '='.repeat(60));
    console.log('[SUMMARY] TEST SUMMARY');
    console.log('='.repeat(60));
    console.log('Total Tests: ' + testResults.total + '');
    console.log('[SUCCESS] Passed: ' + testResults.passed.length + '');
    console.log('[FAIL] Failed: ' + testResults.failed.length + '');
    console.log('Success Rate: ' + ((testResults.passed.length / testResults.total) * 100).toFixed(1) + '%');
    
    // Performance summary
    console.log('\n[PERF] Performance Metrics:');
    console.log('   Average Response Time: ' + testResults.performance.averageResponseTime + 'ms');
    if (testResults.performance.averageStreamingTime > 0) {
        console.log('   Average Streaming Time: ' + testResults.performance.averageStreamingTime + 'ms');
    }
    console.log('   Fastest Response: ' + (testResults.performance.fastestResponse === Infinity ? 'N/A' : testResults.performance.fastestResponse + 'ms'));
    console.log('   Slowest Response: ' + testResults.performance.slowestResponse + 'ms');
    
    // Data quality summary
    const qualityCounts = {
        excellent: 0,
        good: 0,
        fair: 0,
        poor: 0,
        unknown: 0
    };
    testResults.allTests.forEach(test => {
        const quality = test.dataQuality || 'unknown';
        qualityCounts[quality] = (qualityCounts[quality] || 0) + 1;
    });
    console.log('\n[DATA] Data Quality Distribution:');
    Object.entries(qualityCounts).forEach(([quality, count]) => {
        if (count > 0) {
            console.log(`   ${quality.charAt(0).toUpperCase() + quality.slice(1)}: ${count} (${((count / testResults.total) * 100).toFixed(1)}%)`);
        }
    });
    
    if (testResults.failed.length > 0) {
        console.log('\n[FAIL] Failed Tests:');
        testResults.failed.forEach((test, i) => {
            console.log(`\n${i + 1}. Question: "${test.question}"`);
            if (test.reason) console.log('   Reason: ' + test.reason + '');
            if (test.error) console.log('   Error: ' + test.error + '');
            if (test.responseTime) console.log('   Response Time: ' + test.responseTime + 'ms');
            if (test.dataQuality) console.log('   Data Quality: ' + test.dataQuality);
            if (test.issues && test.issues.length > 0) {
                console.log('   Validation Issues: ' + test.issues.join(', '));
            }
            if (test.response) console.log('   Response: ' + test.response.substring(0, 100) + '...');
        });
    }
    
    console.log('\n' + '='.repeat(60));
}

// Run tests
if (require.main === module) {
    // Check command line arguments
    const args = process.argv.slice(2);
    const useStatic = args.includes('--static') || args.includes('-s');
    
    runTests(useStatic).catch(console.error);
}

/**
 * Generate PDF report with all questions and answers
 */
async function generatePDFReport() {
    console.log('\n[PDF] Generating PDF report...');
    
    const outputDir = path.join(__dirname, 'reports');
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }
    
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
    const filename = `test-report-${timestamp}.pdf`;
    const filepath = path.join(outputDir, filename);
    
    const doc = new PDFDocument({ margin: 50 });
    const stream = fs.createWriteStream(filepath);
    doc.pipe(stream);
    
    // Header
    doc.fontSize(20).text('Dataviz AI WooCommerce Plugin', { align: 'center' });
    doc.fontSize(16).text('Test Report', { align: 'center' });
    doc.moveDown();
    doc.fontSize(10).text(`Generated: ${new Date().toLocaleString()}`, { align: 'center' });
    doc.moveDown(2);
    
    // Summary
    doc.fontSize(14).text('Test Summary', { underline: true });
    doc.moveDown(0.5);
    doc.fontSize(11);
    doc.text(`Total Tests: ${testResults.total}`);
    doc.text(`Passed: ${testResults.passed.length}`, { continued: true, indent: 20 });
    doc.text(`Failed: ${testResults.failed.length}`, { continued: true, indent: 20 });
    doc.text(`Success Rate: ${((testResults.passed.length / testResults.total) * 100).toFixed(1)}%`);
    doc.moveDown(2);
    
    // Test Results
    doc.fontSize(14).text('Test Results', { underline: true });
    doc.moveDown();
    
    testResults.allTests.forEach((test, index) => {
        // Add page break if needed (every 3 tests)
        if (index > 0 && index % 3 === 0) {
            doc.addPage();
        }
        
        // Test number and status
        const status = test.passed ? '✅ PASSED' : '❌ FAILED';
        doc.fontSize(12).fillColor(test.passed ? 'green' : 'red');
        doc.text(`Test ${index + 1}: ${status}`, { continued: false });
        doc.fillColor('black');
        doc.moveDown(0.3);
        
        // Question
        doc.fontSize(11).fillColor('blue');
        doc.text('Question:', { continued: false });
        doc.fillColor('black');
        doc.fontSize(10);
        doc.text(test.question, { indent: 20, align: 'left' });
        doc.moveDown(0.5);
        
        // Response
        doc.fontSize(11).fillColor('blue');
        doc.text('Response:', { continued: false });
        doc.fillColor('black');
        doc.fontSize(9);
        
        // Wrap long responses
        const maxWidth = 500;
        const responseLines = doc.heightOfString(test.response, { width: maxWidth });
        if (responseLines > 15) {
            // Truncate very long responses
            const truncated = test.response.substring(0, 500) + '...';
            doc.text(truncated, { indent: 20, width: maxWidth });
        } else {
            doc.text(test.response, { indent: 20, width: maxWidth });
        }
        doc.moveDown(0.5);
        
        // Additional info
        if (test.chartDisplayed) {
            doc.fontSize(9).fillColor('green');
            doc.text('📊 Chart displayed', { indent: 20 });
            doc.fillColor('black');
        }
        
        // Performance metrics
        if (test.performanceMetrics) {
            doc.fontSize(9).fillColor('blue');
            doc.text(`⏱️ Response: ${test.performanceMetrics.responseTime}ms`, { indent: 20 });
            if (test.performanceMetrics.streamingTime) {
                doc.text(`   Streaming: ${test.performanceMetrics.streamingTime}ms`, { indent: 20 });
            }
            doc.fillColor('black');
        }
        
        // Data quality
        if (test.dataQuality) {
            const qualityColors = {
                excellent: 'green',
                good: 'blue',
                fair: 'orange',
                poor: 'red'
            };
            doc.fontSize(9).fillColor(qualityColors[test.dataQuality] || 'black');
            doc.text(`📊 Data Quality: ${test.dataQuality}`, { indent: 20 });
            doc.fillColor('black');
        }
        
        // Validation info
        if (test.dataValidation && test.dataValidation.validations.length > 0) {
            doc.fontSize(9).fillColor('green');
            doc.text(`✓ Validations: ${test.dataValidation.validations.join(', ')}`, { indent: 20 });
            doc.fillColor('black');
        }
        
        if (test.dataValidation && test.dataValidation.issues.length > 0) {
            doc.fontSize(9).fillColor('red');
            doc.text(`⚠ Issues: ${test.dataValidation.issues.join(', ')}`, { indent: 20 });
            doc.fillColor('black');
        }
        
        if (test.reason && !test.passed) {
            doc.fontSize(9).fillColor('red');
            doc.text(`Reason: ${test.reason}`, { indent: 20 });
            doc.fillColor('black');
        }
        
        doc.moveDown(1);
        
        // Add separator line
        doc.moveTo(50, doc.y).lineTo(550, doc.y).stroke();
        doc.moveDown(0.5);
    });
    
    // Failed tests summary (if any)
    if (testResults.failed.length > 0) {
        doc.addPage();
        doc.fontSize(14).text('Failed Tests Summary', { underline: true });
        doc.moveDown();
        
        testResults.failed.forEach((test, index) => {
            doc.fontSize(11).fillColor('red');
            doc.text(`${index + 1}. ${test.question}`, { continued: false });
            doc.fillColor('black');
            doc.fontSize(9);
            if (test.reason) {
                doc.text(`   Reason: ${test.reason}`, { indent: 20 });
            }
            if (test.error) {
                doc.text(`   Error: ${test.error}`, { indent: 20 });
            }
            doc.moveDown(0.5);
        });
    }
    
    // Footer on last page
    doc.fontSize(8).fillColor('gray');
    doc.text('Generated by Dataviz AI Test Agent', 50, doc.page.height - 50, { align: 'center' });
    
    doc.end();
    
    return new Promise((resolve, reject) => {
        stream.on('finish', () => {
            console.log(`[SUCCESS] PDF report generated: ${filepath}`);
            resolve(filepath);
        });
        stream.on('error', reject);
    });
}

module.exports = { 
    runTests, 
    testChatQuestion, 
    verifyResponse, 
    generatePDFReport,
    generateTestQuestions,
    getPredefinedQuestions,
    loadSavedQuestions,
    saveQuestions
};


