// Test streaming behavior with our updated formatResponsePreservingImages
// This simulates how text arrives character by character during streaming

// Sample response that arrives in chunks during streaming
const streamingResponse = "이것은 **굵은 텍스트**입니다. 그리고 여기 이미지가 있습니다: [http://192.168.10.101:8001/images/03ff1bb5.jpg](http://192.168.10.101:8001/images/03ff1bb5.jpg) 이미지 뒤의 *기울임* 텍스트입니다.";

// Simplified version of formatResponsePreservingImages for testing
function mockFormatResponsePreservingImages(text) {
    console.log('🖼️ [STREAM TEST] Processing text length:', text.length);
    
    if (!text || typeof text !== 'string') {
        console.warn('🖼️ [STREAM TEST] Invalid input:', typeof text);
        return text || '';
    }
    
    const imagePlaceholders = {};
    let imageCounter = 0;
    
    // 1. Double URL pattern - simplified
    const simpleDoubleUrlPattern = /\[(https?:\/\/[^\]]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\]]*)\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\)]*)\)/gi;
    const doubleUrlMatches = text.match(simpleDoubleUrlPattern);
    
    if (doubleUrlMatches) {
        console.log('🖼️ [STREAM TEST] Found double URL matches:', doubleUrlMatches.length);
        text = text.replace(simpleDoubleUrlPattern, function(match, url1, ext1, url2, ext2) {
            if (url1 === url2 || Math.abs(url1.length - url2.length) <= 3) {
                const finalUrl = url1.length >= url2.length ? url1 : url2;
                const imgTag = `<img src="proxy-${finalUrl}" alt="Image">`;
                const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                imagePlaceholders[placeholder] = imgTag;
                console.log('🖼️ [STREAM TEST] Created placeholder:', placeholder);
                return placeholder;
            }
            return match;
        });
    }
    
    // 2. Fallback pattern for image server URLs
    const fallbackPattern = /\[(https?:\/\/192\.168\.10\.101:8001\/[^\]]+)\]\((https?:\/\/192\.168\.10\.101:8001\/[^\)]+)\)/gi;
    const fallbackMatches = text.match(fallbackPattern);
    
    if (fallbackMatches) {
        console.log('🖼️ [STREAM TEST] Found fallback matches:', fallbackMatches.length);
        text = text.replace(fallbackPattern, function(match, url1, url2) {
            if ((url1 === url2 || Math.abs(url1.length - url2.length) <= 3) && 
                (url1.includes('/images/') || url2.includes('/images/'))) {
                const finalUrl = url1.length >= url2.length ? url1 : url2;
                const imgTag = `<img src="proxy-${finalUrl}" alt="Image">`;
                const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
                imagePlaceholders[placeholder] = imgTag;
                console.log('🖼️ [STREAM TEST] Created fallback placeholder:', placeholder);
                return placeholder;
            }
            return match;
        });
    }
    
    // Simple markdown processing
    let formatted = text
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>');
    
    // Restore placeholders
    Object.keys(imagePlaceholders).forEach(placeholder => {
        formatted = formatted.replace(new RegExp(placeholder, 'g'), imagePlaceholders[placeholder]);
    });
    
    console.log('🖼️ [STREAM TEST] Final result:', formatted.length > 100 ? formatted.substring(0, 100) + '...' : formatted);
    return formatted;
}

// Simulate streaming by processing increasing substrings
function testStreamingBehavior() {
    console.log('=== Testing Streaming Behavior ===\n');
    
    // Test at different stages of streaming
    const testLengths = [20, 50, 80, 120, 150, 200, streamingResponse.length];
    
    testLengths.forEach(length => {
        const chunk = streamingResponse.substring(0, Math.min(length, streamingResponse.length));
        console.log(`\n--- Testing chunk of length ${chunk.length} ---`);
        console.log('Input chunk:', chunk);
        const result = mockFormatResponsePreservingImages(chunk);
        console.log('Formatted:', result);
        console.log('Has img tags:', result.includes('<img'));
        console.log('Has placeholders:', result.includes('__IMAGE_PLACEHOLDER_'));
    });
}

// Test with various edge cases
function testEdgeCases() {
    console.log('\n=== Testing Edge Cases ===\n');
    
    const edgeCases = [
        '',
        null,
        undefined,
        '[http://192.168.10.101:8001/images/test.jpg](http://192.168.10.101:8001/images/test.jpg)',
        '[http://192.168.10.101:8001/images/incomplete.jpg](http://192.168.10.101:8001/images/inco',
        'Normal text without images',
        '[http://192.168.10.101:8001/images/no-extension](http://192.168.10.101:8001/images/no-extension)',
        '**Bold** [http://192.168.10.101:8001/images/mixed.png](http://192.168.10.101:8001/images/mixed.png) *italic*'
    ];
    
    edgeCases.forEach((testCase, index) => {
        console.log(`\n--- Edge Case ${index + 1} ---`);
        console.log('Input:', testCase);
        const result = mockFormatResponsePreservingImages(testCase);
        console.log('Result:', result);
    });
}

// Run tests
testStreamingBehavior();
testEdgeCases();

console.log('\n=== Streaming Test Complete ===');