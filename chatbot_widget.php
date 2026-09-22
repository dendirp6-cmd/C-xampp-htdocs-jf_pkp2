<?php
// FILE: chatbot_widget.php - Berisi markup dan skrip untuk chatbot popup.

// Pastikan untuk HANYA menempelkan kode ini di dalam tag <body>
// dari template utama Anda (misalnya: footer.php)

// KUNCI API ANDA (TETAP DI SINI UNTUK KEMUDAHAN)
$apiKey = "AIzaSyDJLsyYloXzNL0Zlmnbe1jhYY0GWIXORUo"; 
?>
<!-- CONTAINER UTAMA WIDGET (Seluruh kode ini akan di-include ke footer) -->
<div id="gemini-chat-widget">
    
    <!-- 1. TOMBOL TOGGLE CHAT (IKON BALON) -->
    <button 
        id="chat-toggle-button"
        onclick="toggleChat()"
        class="w-14 h-14 bg-indigo-600 text-white rounded-full flex items-center justify-center shadow-xl hover:bg-indigo-700 transition duration-300 transform hover:scale-105 ml-auto">
        <svg id="chat-icon-open" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 4v-4z"></path></svg>
        <svg id="chat-icon-close" class="w-7 h-7 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>

    <!-- 2. JENDELA CHAT UTAMA (POPUP) -->
    <div id="chat-popup-window" class="absolute bottom-0 right-0 mb-16 bg-white shadow-2xl rounded-xl overflow-hidden flex flex-col">
        
        <!-- Header Chatbot -->
        <header class="bg-indigo-600 p-4 text-white text-lg font-semibold flex items-center shadow-md">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            Gemini Assistant
        </header>

        <!-- Jendela Chat -->
        <div id="chat-window" class="flex-grow p-4 space-y-4 overflow-y-auto">
            <!-- Pesan Pembuka dari Bot -->
            <div class="flex justify-start">
                <div class="bot-message p-3 max-w-xs rounded-xl rounded-tl-none shadow-sm">
                    Halo! Saya asisten Gemini. Ada yang bisa saya bantu?
                </div>
            </div>
        </div>

        <!-- Area Input dan Tombol Kirim -->
        <footer class="p-4 border-t border-gray-200">
            <div class="flex">
                <input 
                    type="text" 
                    id="user-input" 
                    placeholder="Ketik pesan Anda..." 
                    class="flex-grow border border-gray-300 rounded-l-lg p-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-150"
                    onkeyup="if(event.key === 'Enter' && this.value.trim() !== '') sendMessage()" 
                    autocomplete="off">
                <button 
                    id="send-button"
                    onclick="sendMessage()" 
                    class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-r-lg font-semibold flex items-center justify-center transition duration-150 ease-in-out disabled:bg-indigo-400">
                    <svg id="send-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    <svg id="loading-spinner" class="w-5 h-5 animate-spin text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </footer>
    </div>
</div>

<!-- STYLING KHUSUS WIDGET -->
<style>
    /* Styling Global untuk Widget */
    #gemini-chat-widget {
        font-family: 'Inter', sans-serif;
        position: fixed; /* Membuat widget mengambang di layar */
        bottom: 20px;
        right: 20px;
        z-index: 9999; /* Pastikan selalu di atas elemen lain */
    }

    /* Styling Jendela Chat (Awalnya Tersembunyi) */
    #chat-popup-window {
        height: 500px; /* Tinggi standar widget */
        width: 350px; /* Lebar standar widget */
        transition: all 0.3s ease-in-out;
        transform: scale(0); /* Awalnya tersembunyi/kecil */
        transform-origin: bottom right;
        opacity: 0;
        pointer-events: none; /* Mencegah interaksi saat tersembunyi */
    }
    
    /* Kelas untuk menampakkan jendela */
    .show-chat-window {
        transform: scale(1) !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    /* Styling Pesan dan Scrollbar */
    #chat-window {
        scrollbar-width: thin;
        scrollbar-color: #d1d5db #f3f4f6;
    }
    #chat-window::-webkit-scrollbar {
        width: 8px;
    }
    #chat-window::-webkit-scrollbar-thumb {
        background-color: #d1d5db;
        border-radius: 10px;
    }
    .user-message {
        background-color: #4f46e5;
        color: white;
    }
    .bot-message {
        background-color: #ffffff;
        color: #1f2937;
    }
</style>

<!-- SKRIP KHUSUS WIDGET -->
<script type="module">
    // Memuat Google Gen AI SDK
    import { GoogleGenAI } from 'https://esm.run/@google/genai';
    
    // --- INISIALISASI VARIABEL DOM ---
    const apiKey = "<?php echo $apiKey; ?>"; // Ambil API Key dari PHP
    const chatPopupWindow = document.getElementById('chat-popup-window');
    const chatWindow = document.getElementById('chat-window');
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const sendIcon = document.getElementById('send-icon');
    const loadingSpinner = document.getElementById('loading-spinner');
    const chatIconOpen = document.getElementById('chat-icon-open');
    const chatIconClose = document.getElementById('chat-icon-close');

    let chatModel; 

    // ----------------------------------------------------
    // FUNGSI UTAMA WIDGET TOGGLE
    // ----------------------------------------------------
    window.toggleChat = function() {
        const isHidden = !chatPopupWindow.classList.contains('show-chat-window');
        
        if (isHidden) {
            chatPopupWindow.classList.add('show-chat-window');
            chatIconOpen.classList.add('hidden');
            chatIconClose.classList.remove('hidden');
        } else {
            chatPopupWindow.classList.remove('show-chat-window');
            chatIconOpen.classList.remove('hidden');
            chatIconClose.classList.add('hidden');
        }
    }

    // ----------------------------------------------------
    // INISIALISASI GEMINI
    // ----------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const ai = new GoogleGenAI({ apiKey });
            chatModel = ai.chats.create({
                model: "gemini-2.5-flash", 
                config: {
                    systemInstruction: "Anda adalah asisten AI yang ramah dan membantu, dirancang untuk memberikan jawaban yang jelas dan ringkas dalam Bahasa Indonesia. Anda adalah chatbot widget yang tertanam di website, jawablah pertanyaan seputar website ini (jika ada) atau pertanyaan umum lainnya."
                }
            });
        } catch (error) {
            console.error("Kesalahan inisialisasi AI:", error);
            addMessage("🚨 Error: Chatbot tidak dapat diinisialisasi.", 'bot');
            setLoading(false); 
        }
    });

    // FUNGSI UNTUK MENAMPILKAN PESAN
    function addMessage(text, sender) {
        const messageContainer = document.createElement('div');
        messageContainer.classList.add('flex', sender === 'user' ? 'justify-end' : 'justify-start');

        const messageBubble = document.createElement('div');
        messageBubble.classList.add(
            'p-3', 
            'max-w-[85%]', 
            'rounded-xl',
            'shadow-md',
            'text-sm',
            'break-words'
        );
        
        if (sender === 'user') {
            messageBubble.classList.add('user-message', 'rounded-br-none');
        } else {
            messageBubble.classList.add('bot-message', 'rounded-tl-none');
        }
        
        let formattedText = text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/(\n)/g, '<br>');

        messageBubble.innerHTML = formattedText;
        messageContainer.appendChild(messageBubble);
        chatWindow.appendChild(messageContainer);
        
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    // FUNGSI UNTUK MENGELOLA LOADING
    function setLoading(isLoading) {
        sendButton.disabled = isLoading;
        userInput.disabled = isLoading;
        sendIcon.classList[isLoading ? 'add' : 'remove']('hidden');
        loadingSpinner.classList[isLoading ? 'remove' : 'add']('hidden');
    }

    // FUNGSI UTAMA MENGIRIM PESAN
    window.sendMessage = async function() {
        const userText = userInput.value.trim();
        if (!userText || !chatModel) {
            if (!chatModel) addMessage("⚠️ Chatbot belum siap. Coba refresh halaman.", 'bot');
            return;
        }

        addMessage(userText, 'user');
        userInput.value = '';
        setLoading(true);

        try {
            const response = await chatModel.sendMessage({ message: userText });

            if (response.text) {
                addMessage(response.text, 'bot');
            } else {
                addMessage("Maaf, AI tidak memberikan respons teks.", 'bot');
            }
        } catch (error) {
            console.error("Kesalahan saat mengirim pesan ke Gemini:", error);
            let errorMessage = "🚨 Gagal terhubung ke Gemini AI.";
            if (error.status === 400) {
                 errorMessage += " **(Kunci API salah/kuota habis)**";
            }
            addMessage(errorMessage, 'bot');
        } finally {
            setLoading(false);
        }
    }
</script>