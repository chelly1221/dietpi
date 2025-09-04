// Test script to verify formatResponsePreservingImages function
// This mimics the actual function from kachi-api.js

function testFormatResponsePreservingImages(text) {
    console.log('🖼️ [TEST] formatResponsePreservingImages input:', text.substring(0, 200) + (text.length > 200 ? '...' : ''));
    
    const imagePlaceholders = {};
    let imageCounter = 0;
    
    // 기존 이미지 태그 보호
    text = text.replace(/<img[^>]*>/g, function(match) {
        console.log('🖼️ [TEST] Protecting existing img tag:', match);
        const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
        imagePlaceholders[placeholder] = match;
        return placeholder;
    });
    
    // 이미지 URL 패턴들을 마크다운 처리 전에 감지하여 보호
    console.log('🖼️ [TEST] Starting URL pattern matching...');
    
    // 1. 이중 URL 패턴: [http://...](http://...) - 단순화된 패턴
    const simpleDoubleUrlPattern = /\[(https?:\/\/[^\]]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\]]*)\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\)]*)\)/gi;
    const doubleUrlMatches = text.match(simpleDoubleUrlPattern);
    console.log('🖼️ [TEST] Double URL pattern matches found:', doubleUrlMatches ? doubleUrlMatches.length : 0, doubleUrlMatches);
    
    text = text.replace(simpleDoubleUrlPattern, function(match, url1, ext1, url2, ext2) {
        console.log('🖼️ [TEST] Double URL match found:', { match, url1, url2 });
        // 두 URL이 같거나 유사한 경우 이미지로 처리
        if (url1 === url2 || Math.abs(url1.length - url2.length) <= 3) {
            const finalUrl = url1.length >= url2.length ? url1 : url2;
            const proxyUrl = `http://192.168.10.101:8001/proxy-image?url=${encodeURIComponent(finalUrl)}`;
            const imgTag = `<img src="${proxyUrl}" alt="Image" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
            
            const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
            imagePlaceholders[placeholder] = imgTag;
            console.log('🖼️ [TEST] Created double URL placeholder:', placeholder, 'for URL:', finalUrl);
            return placeholder;
        }
        console.log('🖼️ [TEST] URLs not similar enough, keeping original:', match);
        return match; // URL이 다른 경우 원래 텍스트 유지
    });
    
    // 2. 일반 마크다운 이미지 패턴: ![alt](http://...) - 단순화된 패턴
    const simpleMarkdownPattern = /!\[([^\]]*)\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp|bmp|svg)[^\)]*)\)/gi;
    const markdownMatches = text.match(simpleMarkdownPattern);
    console.log('🖼️ [TEST] Markdown image pattern matches found:', markdownMatches ? markdownMatches.length : 0, markdownMatches);
    
    text = text.replace(simpleMarkdownPattern, function(match, alt, url, ext) {
        console.log('🖼️ [TEST] Markdown image match found:', { match, alt, url });
        const proxyUrl = `http://192.168.10.101:8001/proxy-image?url=${encodeURIComponent(url)}`;
        const imgTag = `<img src="${proxyUrl}" alt="${alt || 'Image'}" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
        
        const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
        imagePlaceholders[placeholder] = imgTag;
        console.log('🖼️ [TEST] Created markdown placeholder:', placeholder, 'for URL:', url);
        return placeholder;
    });
    
    // 3. 단순 URL 패턴 (독립된 줄에 있는 경우) - 단순화된 패턴
    const simplePlainUrlPattern = /^\s*(https?:\/\/\S+\.(jpg|jpeg|png|gif|webp|bmp|svg)\S*)\s*$/gmi;
    const plainUrlMatches = text.match(simplePlainUrlPattern);
    console.log('🖼️ [TEST] Plain URL pattern matches found:', plainUrlMatches ? plainUrlMatches.length : 0, plainUrlMatches);
    
    text = text.replace(simplePlainUrlPattern, function(match, url, ext) {
        console.log('🖼️ [TEST] Plain URL match found:', { match, url });
        const proxyUrl = `http://192.168.10.101:8001/proxy-image?url=${encodeURIComponent(url)}`;
        const imgTag = `<img src="${proxyUrl}" alt="Image" style="max-width: 100%; height: auto; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" loading="lazy" onerror="this.style.display='none'">`;
        
        const placeholder = `__IMAGE_PLACEHOLDER_${imageCounter++}__`;
        imagePlaceholders[placeholder] = imgTag;
        console.log('🖼️ [TEST] Created plain URL placeholder:', placeholder, 'for URL:', url);
        return placeholder;
    });
    
    // 간단한 마크다운 처리 (테스트용)
    const formatted = text
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
    
    // 이미지 플레이스홀더를 원래 태그로 복원
    console.log('🖼️ [TEST] Total placeholders created:', Object.keys(imagePlaceholders).length, imagePlaceholders);
    let result = formatted;
    Object.keys(imagePlaceholders).forEach(placeholder => {
        const beforeLength = result.length;
        result = result.replace(new RegExp(placeholder, 'g'), imagePlaceholders[placeholder]);
        const afterLength = result.length;
        console.log('🖼️ [TEST] Restored placeholder:', placeholder, 'length change:', afterLength - beforeLength);
    });
    
    console.log('🖼️ [TEST] Final formatted output length:', result.length);
    console.log('🖼️ [TEST] Final output preview:', result.substring(0, 300) + (result.length > 300 ? '...' : ''));
    return result;
}

// 테스트 케이스들
const testInputs = [
    "[http://192.168.10.101:8001/images/03ff1bb5.jpg](http://192.168.10.101:8001/images/03ff1bb5.jpg)",
    "![Sample](http://192.168.10.101:8001/images/sample.png)",
    "http://192.168.10.101:8001/images/plain.jpg",
    "**Bold text** with [http://192.168.10.101:8001/images/mixed.jpg](http://192.168.10.101:8001/images/mixed.jpg)",
    "Multiple:\n![img1](http://192.168.10.101:8001/images/img1.png)\n[http://192.168.10.101:8001/images/img2.jpg](http://192.168.10.101:8001/images/img2.jpg)"
];

console.log('Starting function tests...\n');

testInputs.forEach((input, index) => {
    console.log(`\n=================== TEST ${index + 1} ===================`);
    console.log('INPUT:', input);
    const result = testFormatResponsePreservingImages(input);
    console.log('FINAL RESULT:', result);
    console.log('=====================================\n');
});