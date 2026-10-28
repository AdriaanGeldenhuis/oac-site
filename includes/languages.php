<?php
/**
 * Language Configuration - Single Source of Truth
 * All supported languages for the OAC App
 */
declare(strict_types=1);

// Supported language codes
define('SUPPORTED_LANGS', ['af', 'en', 'zu', 'xh', 'pt', 'st']);

// Display names for each language
define('LANG_NAMES', [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Português',
    'st' => 'Sesotho'
]);

// Bible file mapping per language
define('BIBLE_FILES', [
    'af' => 'af_1933_53.json',
    'en' => 'en_kjv1611.json',
    'zu' => 'zu_dummy.json',
    'xh' => 'xh_dummy.json',
    'pt' => 'pt_dummy.json',
    'st' => 'st_dummy.json'
]);

// Office/Amp ID to translation key mapping
// Maps amp_id to [male_key, female_key]
define('AMP_TRANSLATION_KEYS', [
    1  => ['apostle', 'apostle_sister'],
    2  => ['overseer', 'overseer_sister'],
    3  => ['evangelist', 'evangelist_sister'],
    4  => ['prophet', 'prophet_sister'],
    5  => ['elder', 'elder_sister'],
    6  => ['priest', 'priest_sister'],
    7  => ['subdeacon', 'subdeacon_sister'],
    8  => ['choir_leader_brother', 'choir_leader_sister'],
    9  => ['helper', 'helper_sister'],
    10 => ['brother', 'sister']
]);

/**
 * Get translated office name based on amp_id and gender
 * @param int $ampId The office ID
 * @param string $gender 'man' or 'vrou'
 * @param string $lang Language code
 * @return string Translated office name
 */
function get_translated_office(int $ampId, string $gender, string $lang): string {
    $keys = AMP_TRANSLATION_KEYS[$ampId] ?? ['member', 'member'];
    $key = (strtolower($gender) === 'vrou') ? $keys[1] : $keys[0];
    return __t($key, $lang);
}

// UI Translations for all pages
define('UI_TRANSLATIONS', [
    // Welcome page
    'welcome' => [
        'af' => 'Welkom',
        'en' => 'Welcome',
        'zu' => 'Siyakwamukela',
        'xh' => 'Wamkelekile',
        'pt' => 'Bem-vindo',
        'st' => 'Rea o amohela'
    ],
    'welcome_title' => [
        'af' => 'Welkom by Die Ou Apostoliese Kerk',
        'en' => 'Welcome to The Old Apostolic Church',
        'zu' => 'Siyakwamukela eBandleni Elidala Labapostoli',
        'xh' => 'Wamkelekile kwiCawe yamaDala amaPostile',
        'pt' => 'Bem-vindo à Igreja Apostólica Antiga',
        'st' => 'Rea o amohela Kerekeng ea Apostola ea Khale'
    ],
    'teaching_of_month' => [
        'af' => 'Lering van die Maand',
        'en' => 'Teaching of the Month',
        'zu' => 'Imfundiso Yenyanga',
        'xh' => 'Imfundiso Yenyanga',
        'pt' => 'Ensino do Mês',
        'st' => 'Thuto ea Khoeli'
    ],
    'grow_faith' => [
        'af' => 'Groei in geloof en kennis',
        'en' => 'Growing in faith and knowledge',
        'zu' => 'Ukukhula ekukholweni nasekwazini',
        'xh' => 'Ukukhula elukholweni naselwazini',
        'pt' => 'Crescendo em fé e conhecimento',
        'st' => 'Ho hola tumelong le tsebong'
    ],
    'no_content' => [
        'af' => 'Geen lering-inhoud gevind nie.',
        'en' => 'No teaching content found.',
        'zu' => 'Akukho okuqukethwe okufundisayo okutholiwe.',
        'xh' => 'Akukho mfundiso ifunyenweyo.',
        'pt' => 'Nenhum conteúdo de ensino encontrado.',
        'st' => 'Ha ho na thuto e fumanoeng.'
    ],
    // Navigation
    'gospel_media' => [
        'af' => 'Evangelie Media',
        'en' => 'Gospel Media',
        'zu' => 'Imidiya YeVangeli',
        'xh' => 'Imidiya yeVangeli',
        'pt' => 'Mídia do Evangelho',
        'st' => 'Media ea Evangeli'
    ],
    'prayers' => [
        'af' => 'Gebede',
        'en' => 'Prayers',
        'zu' => 'Imithandazo',
        'xh' => 'Imithandazo',
        'pt' => 'Orações',
        'st' => 'Lithapelo'
    ],
    'bible' => [
        'af' => 'Bybel',
        'en' => 'Bible',
        'zu' => 'IBhayibheli',
        'xh' => 'IBhayibhile',
        'pt' => 'Bíblia',
        'st' => 'Bibele'
    ],
    'calendar' => [
        'af' => 'Kalender',
        'en' => 'Calendar',
        'zu' => 'Ikhalenda',
        'xh' => 'Ikhalenda',
        'pt' => 'Calendário',
        'st' => 'Khalendara'
    ],
    'diary' => [
        'af' => 'Dagboek',
        'en' => 'Diary',
        'zu' => 'Idayari',
        'xh' => 'Idayari',
        'pt' => 'Diário',
        'st' => 'Buka ea Letsatsi'
    ],
    'notifications' => [
        'af' => 'Kennisgewings',
        'en' => 'Notifications',
        'zu' => 'Izaziso',
        'xh' => 'Izaziso',
        'pt' => 'Notificações',
        'st' => 'Litsebiso'
    ],
    'admin' => [
        'af' => 'Admin',
        'en' => 'Admin',
        'zu' => 'Umlawuli',
        'xh' => 'Umlawuli',
        'pt' => 'Admin',
        'st' => 'Tsamaiso'
    ],
    'logout' => [
        'af' => 'Teken uit',
        'en' => 'Log out',
        'zu' => 'Phuma',
        'xh' => 'Phuma',
        'pt' => 'Sair',
        'st' => 'Tsoa'
    ],
    'privacy_policy' => [
        'af' => 'Privaatheidsbeleid',
        'en' => 'Privacy Policy',
        'zu' => 'Inqubomgomo Yobumfihlo',
        'xh' => 'Umgaqo-nkqubo Wabucala',
        'pt' => 'Política de Privacidade',
        'st' => 'Leano la Lekunutu'
    ],
    'navigation' => [
        'af' => 'Navigasie',
        'en' => 'Navigation',
        'zu' => 'Ukuzulazula',
        'xh' => 'Ukuhamba',
        'pt' => 'Navegação',
        'st' => 'Ho tsamaea'
    ],
    'close' => [
        'af' => 'Sluit',
        'en' => 'Close',
        'zu' => 'Vala',
        'xh' => 'Vala',
        'pt' => 'Fechar',
        'st' => 'Koala'
    ],
    'open_menu' => [
        'af' => 'Open menu',
        'en' => 'Open menu',
        'zu' => 'Vula imenyu',
        'xh' => 'Vula imenyu',
        'pt' => 'Abrir menu',
        'st' => 'Bula menu'
    ],
    'toggle_theme' => [
        'af' => 'Wissel Tema',
        'en' => 'Toggle Theme',
        'zu' => 'Shintsha Ithimu',
        'xh' => 'Tshintsha Ithimu',
        'pt' => 'Alternar Tema',
        'st' => 'Fetola Tema'
    ],
    'toggle_view' => [
        'af' => 'Wissel Aansig',
        'en' => 'Toggle View',
        'zu' => 'Shintsha Umbono',
        'xh' => 'Tshintsha Imbonakalo',
        'pt' => 'Alternar Visualização',
        'st' => 'Fetola Pono'
    ],
    'ai_smart_bible' => [
        'af' => 'AI Slimbybel',
        'en' => 'AI Smart Bible',
        'zu' => 'IBhayibheli Elihlakaniphile le-AI',
        'xh' => 'IBhayibhile eKreleyayo ye-AI',
        'pt' => 'Bíblia Inteligente IA',
        'st' => 'Bibele e Bohlale ea AI'
    ],
    'sing_emmanuel' => [
        'af' => 'Sing Emmanuel',
        'en' => 'Sing Emmanuel',
        'zu' => 'Cula Emmanuel',
        'xh' => 'Cula Emmanuel',
        'pt' => 'Cantar Emmanuel',
        'st' => 'Bina Emmanuel'
    ],
    // Page titles
    'page_title_welcome' => [
        'af' => 'Welkom - OAC APP',
        'en' => 'Welcome - OAC APP',
        'zu' => 'Siyakwamukela - OAC APP',
        'xh' => 'Wamkelekile - OAC APP',
        'pt' => 'Bem-vindo - OAC APP',
        'st' => 'Rea o amohela - OAC APP'
    ],
    // Scripture quotes
    'quote_matthew_18_20' => [
        'af' => 'Want waar twee of drie in My Naam vergader is, daar is Ek in hulle midde.',
        'en' => 'For where two or three gather in my name, there am I with them.',
        'zu' => 'Ngoba lapho ababili noma abathathu behlangene egameni lami, ngikhona phakathi kwabo.',
        'xh' => 'Kuba apho kubandakanya ababini okanye abathathu egameni lam, ndilapho phakathi kwabo.',
        'pt' => 'Pois onde dois ou três estão reunidos em meu nome, ali estou eu no meio deles.',
        'st' => 'Hobane moo ba babeli kapa ba bararo ba kopaneng ka lebitso la ka, ke teng hara bona.'
    ],
    'quote_matthew_18_20_ref' => [
        'af' => 'Matthéüs 18:20',
        'en' => 'Matthew 18:20',
        'zu' => 'UMathewu 18:20',
        'xh' => 'UMateyu 18:20',
        'pt' => 'Mateus 18:20',
        'st' => 'Mattheu 18:20'
    ],

    // Welcome page redesign.
    // zu/xh/st are intentionally omitted where no reviewed translation exists:
    // __t() falls back to English for those, which beats showing a wrong word.
    // The greetings use the standard all-day greeting for zu/xh/st.
    'greeting_morning' => [
        'af' => 'Goeiemôre',
        'en' => 'Good morning',
        'zu' => 'Sawubona',
        'xh' => 'Molo',
        'pt' => 'Bom dia',
        'st' => 'Dumela'
    ],
    'greeting_afternoon' => [
        'af' => 'Goeiemiddag',
        'en' => 'Good afternoon',
        'zu' => 'Sawubona',
        'xh' => 'Molo',
        'pt' => 'Boa tarde',
        'st' => 'Dumela'
    ],
    'greeting_evening' => [
        'af' => 'Goeienaand',
        'en' => 'Good evening',
        'zu' => 'Sawubona',
        'xh' => 'Molo',
        'pt' => 'Boa noite',
        'st' => 'Dumela'
    ],
    'up_next' => [
        'af' => 'Volgende',
        'en' => 'Up next',
        'zu' => 'Okulandelayo',
        'xh' => 'Okulandelayo',
        'pt' => 'A seguir',
        'st' => 'Se latelang'
    ],
    'tomorrow' => [
        'af' => 'Môre',
        'en' => 'Tomorrow',
        'zu' => 'Kusasa',
        'xh' => 'Ngomso',
        'pt' => 'Amanhã',
        'st' => 'Hosane'
    ],
    'copied' => [
        'af' => 'Gekopieer',
        'en' => 'Copied',
        'zu' => 'Kukopishiwe',
        'xh' => 'Kukhutshelwe',
        'pt' => 'Copiado',
        'st' => 'E kopilitsoe'
    ],
    'read_more' => [
        'af' => 'Lees meer',
        'en' => 'Read more',
        'zu' => 'Funda kabanzi',
        'xh' => 'Funda ngakumbi',
        'pt' => 'Ler mais',
        'st' => 'Bala haholoanyane'
    ],
    'read_less' => [
        'af' => 'Lees minder',
        'en' => 'Read less',
        'zu' => 'Bonisa okuncane',
        'xh' => 'Bonisa okuncinci',
        'pt' => 'Ler menos',
        'st' => 'Bontsha hanyane'
    ],

    // ==================== ADMIN SECTION ====================
    'admin_dashboard' => [
        'af' => 'Admin Bestuur',
        'en' => 'Admin Dashboard',
        'zu' => 'Iphaneli Yokuphatha',
        'xh' => 'Iphaneli Yolawulo',
        'pt' => 'Painel Admin',
        'st' => 'Phanele ea Tsamaiso'
    ],
    'admin_subtitle' => [
        'af' => 'Beheer jou profiel, goedkeurings en instellings',
        'en' => 'Manage your profile, approvals and settings',
        'zu' => 'Phatha iphrofayela yakho, izimvume nezilungiselelo',
        'xh' => 'Lawula iprofayile yakho, imvume kunye neesetingi',
        'pt' => 'Gerencie seu perfil, aprovações e configurações',
        'st' => 'Laola profaele ea hao, litumello le litlhophiso'
    ],
    'profile' => [
        'af' => 'Profiel',
        'en' => 'Profile',
        'zu' => 'Iphrofayela',
        'xh' => 'Iprofayile',
        'pt' => 'Perfil',
        'st' => 'Profaele'
    ],
    'accounts' => [
        'af' => 'Rekeninge',
        'en' => 'Accounts',
        'zu' => 'Ama-akhawunti',
        'xh' => 'Iiakhawunti',
        'pt' => 'Contas',
        'st' => 'Li-akhaonto'
    ],
    'teaching' => [
        'af' => 'Lering',
        'en' => 'Teaching',
        'zu' => 'Imfundiso',
        'xh' => 'Imfundiso',
        'pt' => 'Ensino',
        'st' => 'Thuto'
    ],
    'offices' => [
        'af' => 'Ampte',
        'en' => 'Officers',
        'zu' => 'Izihamba',
        'xh' => 'Iiofisi',
        'pt' => 'Cargos',
        'st' => 'Likantoro'
    ],
    'appointments' => [
        'af' => 'Afsprake',
        'en' => 'Appointments',
        'zu' => 'Izicelo',
        'xh' => 'Iintlanganiso',
        'pt' => 'Compromissos',
        'st' => 'Likopano'
    ],
    'settings' => [
        'af' => 'Instellings',
        'en' => 'Settings',
        'zu' => 'Izilungiselelo',
        'xh' => 'Iisetingi',
        'pt' => 'Configurações',
        'st' => 'Litlhophiso'
    ],
    'approvals' => [
        'af' => 'Goedkeurings',
        'en' => 'Approvals',
        'zu' => 'Izimvume',
        'xh' => 'Imvume',
        'pt' => 'Aprovações',
        'st' => 'Litumello'
    ],
    'edit_profile' => [
        'af' => 'Wysig Profiel',
        'en' => 'Edit Profile',
        'zu' => 'Hlela Iphrofayela',
        'xh' => 'Hlela Iprofayile',
        'pt' => 'Editar Perfil',
        'st' => 'Fetola Profaele'
    ],
    'update_personal_info' => [
        'af' => 'Werk jou persoonlike inligting by',
        'en' => 'Update your personal information',
        'zu' => 'Buyekeza ulwazi lwakho lomuntu siqu',
        'xh' => 'Hlaziya ulwazi lwakho lobuqu',
        'pt' => 'Atualize suas informações pessoais',
        'st' => 'Ntlafatsa lintlha tsa hao'
    ],
    'spouse_request_sent' => [
        'af' => 'Eggenoot versoek gestuur. Wag vir goedkeuring.',
        'en' => 'Spouse request sent. Waiting for approval.',
        'zu' => 'Isicelo somshado sithunyelwe. Ilinde ukuvunywa.',
        'xh' => 'Isicelo somyeni sithunyelwe. Silindele ukuvunywa.',
        'pt' => 'Pedido de cônjuge enviado. Aguardando aprovação.',
        'st' => 'Kopo ea molekane e rometsoe. E emetse tumello.'
    ],
    'spouse_request_already_pending' => [
        'af' => 'Daar is reeds \'n hangende eggenoot versoek. Wag vir \'n antwoord of kanselleer dit eers.',
        'en' => 'There is already a pending spouse request. Wait for a response or cancel it first.',
        'zu' => 'Sekuvele kunesicelo somshado esilindile. Linda impendulo noma usikhansele kuqala.',
        'xh' => 'Sele kukho isicelo somyeni esilindileyo. Linda impendulo okanye usirhoxise kuqala.',
        'pt' => 'Já existe um pedido de cônjuge pendente. Aguarde uma resposta ou cancele-o primeiro.',
        'st' => 'Ho se ho na le kopo ea molekane e emetseng. Ema karabo kapa u e hlakole pele.'
    ],
    'cancel_request' => [
        'af' => 'Kanselleer Versoek',
        'en' => 'Cancel Request',
        'zu' => 'Khansela Isicelo',
        'xh' => 'Rhoxisa Isicelo',
        'pt' => 'Cancelar Pedido',
        'st' => 'Hlakola Kopo'
    ],
    'profile_updated' => [
        'af' => 'Profiel suksesvol opgedateer',
        'en' => 'Profile updated successfully',
        'zu' => 'Iphrofayela ibuyekezwe ngempumelelo',
        'xh' => 'Iprofayile ihlaziywe ngempumelelo',
        'pt' => 'Perfil atualizado com sucesso',
        'st' => 'Profaele e ntlafalitsoe ka katleho'
    ],
    'error' => [
        'af' => 'Fout',
        'en' => 'Error',
        'zu' => 'Iphutha',
        'xh' => 'Impazamo',
        'pt' => 'Erro',
        'st' => 'Phoso'
    ],
    'profile_no_town' => [
        'af' => 'Jou profiel het geen dorp nie. Kontak admin.',
        'en' => 'Your profile has no town. Contact admin.',
        'zu' => 'Iphrofayela yakho ayinalo idolobha. Xhumana no-admin.',
        'xh' => 'Iprofayile yakho ayinayo idolophu. Qhagamshelana no-admin.',
        'pt' => 'Seu perfil não tem cidade. Contate o administrador.',
        'st' => 'Profaele ea hao ha e na toropo. Ikopanye le motsamaisi.'
    ],
    'you_sent_spouse_request' => [
        'af' => 'Jy het \'n eggenoot versoek gestuur na',
        'en' => 'You sent a spouse request to',
        'zu' => 'Uthumele isicelo somshado ku',
        'xh' => 'Uthumele isicelo somyeni ku',
        'pt' => 'Você enviou um pedido de cônjuge para',
        'st' => 'O romeletse kopo ea molekane ho'
    ],
    'waiting_approval' => [
        'af' => 'Wag vir goedkeuring.',
        'en' => 'Waiting for approval.',
        'zu' => 'Ilinde ukuvunywa.',
        'xh' => 'Ilindele ukuvunywa.',
        'pt' => 'Aguardando aprovação.',
        'st' => 'E emetse tumello.'
    ],
    'wants_to_marry_you' => [
        'af' => 'wil met jou trou.',
        'en' => 'wants to marry you.',
        'zu' => 'ufuna ukushada nawe.',
        'xh' => 'ufuna ukutshata nawe.',
        'pt' => 'quer se casar com você.',
        'st' => 'o batla ho nyalana le uena.'
    ],
    'accept' => [
        'af' => 'Aanvaar',
        'en' => 'Accept',
        'zu' => 'Yamukela',
        'xh' => 'Yamkela',
        'pt' => 'Aceitar',
        'st' => 'Amohela'
    ],
    'reject' => [
        'af' => 'Verwerp',
        'en' => 'Reject',
        'zu' => 'Yenqaba',
        'xh' => 'Yala',
        'pt' => 'Rejeitar',
        'st' => 'Hana'
    ],
    'profile_photo' => [
        'af' => 'Profiel Foto',
        'en' => 'Profile Photo',
        'zu' => 'Isithombe Sephrofayela',
        'xh' => 'Ifoto Yeprofayile',
        'pt' => 'Foto do Perfil',
        'st' => 'Senepe sa Profaele'
    ],
    'photo_hint' => [
        'af' => 'Kies \'n foto om op te laai. Die foto sal outomaties 600x600px wees.',
        'en' => 'Choose a photo to upload. The photo will be automatically resized to 600x600px.',
        'zu' => 'Khetha isithombe ozosifaka. Isithombe sizoshintshwa ngokuzenzakalela sibe 600x600px.',
        'xh' => 'Khetha ifoto ozoyilayisha. Ifoto iya kutshintshelwa ngokuzenzekelayo ku-600x600px.',
        'pt' => 'Escolha uma foto para enviar. A foto será redimensionada automaticamente para 600x600px.',
        'st' => 'Khetha senepe ho se kenya. Senepe se tla fetoha 600x600px ka bo eona.'
    ],
    'name' => [
        'af' => 'Naam',
        'en' => 'Name',
        'zu' => 'Igama',
        'xh' => 'Igama',
        'pt' => 'Nome',
        'st' => 'Lebitso'
    ],
    'surname' => [
        'af' => 'Van',
        'en' => 'Surname',
        'zu' => 'Isibongo',
        'xh' => 'Ifani',
        'pt' => 'Sobrenome',
        'st' => 'Fane'
    ],
    'phone' => [
        'af' => 'Selfoon',
        'en' => 'Phone',
        'zu' => 'Ucingo',
        'xh' => 'Ifowuni',
        'pt' => 'Telefone',
        'st' => 'Mohala'
    ],
    'language' => [
        'af' => 'Taal',
        'en' => 'Language',
        'zu' => 'Ulimi',
        'xh' => 'Ulwimi',
        'pt' => 'Idioma',
        'st' => 'Puo'
    ],
    'birthdate' => [
        'af' => 'Geboortedatum',
        'en' => 'Birthdate',
        'zu' => 'Usuku Lokuzalwa',
        'xh' => 'Umhla Wokuzalwa',
        'pt' => 'Data de Nascimento',
        'st' => 'Letsatsi la Tsoalo'
    ],
    'marital_status' => [
        'af' => 'Huwelikstatus',
        'en' => 'Marital Status',
        'zu' => 'Isimo Somshado',
        'xh' => 'Imeko Yomtshato',
        'pt' => 'Estado Civil',
        'st' => 'Boemo ba Lenyalo'
    ],
    'select' => [
        'af' => 'Kies',
        'en' => 'Select',
        'zu' => 'Khetha',
        'xh' => 'Khetha',
        'pt' => 'Selecionar',
        'st' => 'Khetha'
    ],
    'married' => [
        'af' => 'Getroud',
        'en' => 'Married',
        'zu' => 'Ushadile',
        'xh' => 'Utshatile',
        'pt' => 'Casado(a)',
        'st' => 'O nyetsoe'
    ],
    'unmarried' => [
        'af' => 'Ongetroud',
        'en' => 'Unmarried',
        'zu' => 'Awushadile',
        'xh' => 'Awutshatanga',
        'pt' => 'Solteiro(a)',
        'st' => 'Ha o nyaloa'
    ],
    'male' => [
        'af' => 'Man',
        'en' => 'Male',
        'zu' => 'Owesilisa',
        'xh' => 'Indoda',
        'pt' => 'Masculino',
        'st' => 'Monna'
    ],
    'female' => [
        'af' => 'Vrou',
        'en' => 'Female',
        'zu' => 'Owesifazane',
        'xh' => 'Umfazi',
        'pt' => 'Feminino',
        'st' => 'Mosali'
    ],
    'man' => [
        'af' => 'Man',
        'en' => 'Male',
        'zu' => 'Owesilisa',
        'xh' => 'Indoda',
        'pt' => 'Masculino',
        'st' => 'Monna'
    ],
    'vrou' => [
        'af' => 'Vrou',
        'en' => 'Female',
        'zu' => 'Owesifazane',
        'xh' => 'Umfazi',
        'pt' => 'Feminino',
        'st' => 'Mosali'
    ],
    'getroud' => [
        'af' => 'Getroud',
        'en' => 'Married',
        'zu' => 'Ushadile',
        'xh' => 'Utshatile',
        'pt' => 'Casado(a)',
        'st' => 'O nyetsoe'
    ],
    'ongetroud' => [
        'af' => 'Ongetroud',
        'en' => 'Unmarried',
        'zu' => 'Awushadile',
        'xh' => 'Awutshatanga',
        'pt' => 'Solteiro(a)',
        'st' => 'Ha o nyaloa'
    ],
    'wewenaar' => [
        'af' => 'Wewenaar',
        'en' => 'Widower',
        'zu' => 'Umfelwa',
        'xh' => 'Umhlolo',
        'pt' => 'Viúvo',
        'st' => 'Mohlolo'
    ],
    'weduwee' => [
        'af' => 'Weduwee',
        'en' => 'Widow',
        'zu' => 'Umfelokazi',
        'xh' => 'Umhlolokazi',
        'pt' => 'Viúva',
        'st' => 'Mohlolohali'
    ],
    'widowed' => [
        'af' => 'Wewenaar/Weduwee',
        'en' => 'Widowed',
        'zu' => 'Umfelokazi',
        'xh' => 'Umhlolokazi',
        'pt' => 'Viúvo(a)',
        'st' => 'Mohlolohali'
    ],
    'divorced' => [
        'af' => 'Geskei',
        'en' => 'Divorced',
        'zu' => 'Ohlukanisile',
        'xh' => 'Oqhawukileyo',
        'pt' => 'Divorciado(a)',
        'st' => 'O hladile'
    ],
    'province' => [
        'af' => 'Provinsie',
        'en' => 'Province',
        'zu' => 'Isifundazwe',
        'xh' => 'IPhondo',
        'pt' => 'Província',
        'st' => 'Provense'
    ],
    'town_city' => [
        'af' => 'Stad/Dorp',
        'en' => 'Town/City',
        'zu' => 'Idolobha',
        'xh' => 'Idolophu',
        'pt' => 'Cidade',
        'st' => 'Toropo'
    ],
    'congregation' => [
        'af' => 'Gemeente',
        'en' => 'Congregation',
        'zu' => 'Ibandla',
        'xh' => 'Ibandla',
        'pt' => 'Congregação',
        'st' => 'Phuthego'
    ],
    'oversight' => [
        'af' => 'Opsienerskap',
        'en' => 'Oversight',
        'zu' => 'Ukuqashwa',
        'xh' => 'Ukongamela',
        'pt' => 'Supervisão',
        'st' => 'Tlhokomelo'
    ],
    'youth' => [
        'af' => 'Jeug',
        'en' => 'Youth',
        'zu' => 'Intsha',
        'xh' => 'Ulutsha',
        'pt' => 'Juventude',
        'st' => 'Batjha'
    ],
    'sunday_school' => [
        'af' => 'Sondagskool',
        'en' => 'Sunday School',
        'zu' => 'Isikole SeSonto',
        'xh' => 'Isikolo SeCawa',
        'pt' => 'Escola Dominical',
        'st' => 'Sekolo sa Sontaha'
    ],
    'room' => [
        'af' => 'Kamer',
        'en' => 'Room',
        'zu' => 'Igumbi',
        'xh' => 'Igumbi',
        'pt' => 'Sala',
        'st' => 'Kamore'
    ],
    'select_province_first' => [
        'af' => 'Kies provinsie eers',
        'en' => 'Select province first',
        'zu' => 'Khetha isifundazwe kuqala',
        'xh' => 'Khetha iphondo kuqala',
        'pt' => 'Selecione a província primeiro',
        'st' => 'Khetha provense pele'
    ],
    'select_town_first' => [
        'af' => 'Kies dorp eers',
        'en' => 'Select town first',
        'zu' => 'Khetha idolobha kuqala',
        'xh' => 'Khetha idolophu kuqala',
        'pt' => 'Selecione a cidade primeiro',
        'st' => 'Khetha toropo pele'
    ],
    'spouse' => [
        'af' => 'Eggenoot/Eggenote',
        'en' => 'Spouse',
        'zu' => 'Umlingani',
        'xh' => 'Umlingani',
        'pt' => 'Cônjuge',
        'st' => 'Molekane'
    ],
    'spouse_hint' => [
        'af' => 'Stuur \'n versoek om jou eggenoot te koppel. Die ander persoon moet goedkeur.',
        'en' => 'Send a request to link your spouse. The other person must approve.',
        'zu' => 'Thumela isicelo sokuxhumanisa umlingani wakho. Omunye umuntu kufanele avume.',
        'xh' => 'Thumela isicelo sokudibanisa umlingani wakho. Omnye umntu kufuneka avume.',
        'pt' => 'Envie um pedido para vincular seu cônjuge. A outra pessoa deve aprovar.',
        'st' => 'Romela kopo ea ho hokahanya molekane oa hao. Motho e mong o tlamehile ho amohela.'
    ],
    'you_are_linked_to' => [
        'af' => 'Jy is gekoppel aan',
        'en' => 'You are linked to',
        'zu' => 'Uxhunyiwe no',
        'xh' => 'Udibanisiwe no',
        'pt' => 'Você está vinculado a',
        'st' => 'O hokahane le'
    ],
    'about' => [
        'af' => 'Oor',
        'en' => 'About',
        'zu' => 'Mayelana',
        'xh' => 'Malunga',
        'pt' => 'Sobre',
        'st' => 'Ka'
    ],
    'about_placeholder' => [
        'af' => 'Vertel ons meer van jouself',
        'en' => 'Tell us more about yourself',
        'zu' => 'Sitshele okwengeziwe ngawe',
        'xh' => 'Sixelele ngakumbi ngawe',
        'pt' => 'Conte-nos mais sobre você',
        'st' => 'Re joetse haholoanyane ka uena'
    ],
    'cancel' => [
        'af' => 'Kanselleer',
        'en' => 'Cancel',
        'zu' => 'Khansela',
        'xh' => 'Rhoxisa',
        'pt' => 'Cancelar',
        'st' => 'Hlakola'
    ],
    'save' => [
        'af' => 'Stoor',
        'en' => 'Save',
        'zu' => 'Gcina',
        'xh' => 'Gcina',
        'pt' => 'Salvar',
        'st' => 'Boloka'
    ],

    // ==================== CHURCH OFFICES (Ampte) ====================
    'apostle' => [
        'af' => 'Apostel',
        'en' => 'Apostle',
        'zu' => 'Umphostoli',
        'xh' => 'UmPostile',
        'pt' => 'Apóstolo',
        'st' => 'Moapostola'
    ],
    'prophet' => [
        'af' => 'Profeet',
        'en' => 'Prophet',
        'zu' => 'Umprofethi',
        'xh' => 'Umprofeti',
        'pt' => 'Profeta',
        'st' => 'Moporofeta'
    ],
    'evangelist' => [
        'af' => 'Evangelis',
        'en' => 'Evangelist',
        'zu' => 'Umvangeli',
        'xh' => 'Umvangeli',
        'pt' => 'Evangelista',
        'st' => 'Moevangeli'
    ],
    'shepherd' => [
        'af' => 'Herder',
        'en' => 'Shepherd',
        'zu' => 'Umalusi',
        'xh' => 'Umalusi',
        'pt' => 'Pastor',
        'st' => 'Modisa'
    ],
    'teacher' => [
        'af' => 'Leraar',
        'en' => 'Teacher',
        'zu' => 'Umfundisi',
        'xh' => 'Umfundisi',
        'pt' => 'Professor',
        'st' => 'Moruti'
    ],
    'elder' => [
        'af' => 'Oudste',
        'en' => 'Elder',
        'zu' => 'Umdala',
        'xh' => 'Umdala',
        'pt' => 'Ancião',
        'st' => 'Moholo'
    ],
    'overseer' => [
        'af' => 'Opsiener',
        'en' => 'Overseer',
        'zu' => 'Umbonisi',
        'xh' => 'Umbonisi',
        'pt' => 'Supervisor',
        'st' => 'Mooki'
    ],
    'deacon' => [
        'af' => 'Diaken',
        'en' => 'Deacon',
        'zu' => 'Umdiakoni',
        'xh' => 'Umdiakoni',
        'pt' => 'Diácono',
        'st' => 'Modiakone'
    ],
    'deaconess' => [
        'af' => 'Diakonesse',
        'en' => 'Deaconess',
        'zu' => 'Umdiakoni wesifazane',
        'xh' => 'Umdiakoni obhinqileyo',
        'pt' => 'Diaconisa',
        'st' => 'Modiakone oa basali'
    ],
    'priest' => [
        'af' => 'Priester',
        'en' => 'Priest',
        'zu' => 'Umpristi',
        'xh' => 'Umbingeleli',
        'pt' => 'Sacerdote',
        'st' => 'Moprista'
    ],
    'priestess' => [
        'af' => 'Priesteres',
        'en' => 'Priestess',
        'zu' => 'Umpristi wesifazane',
        'xh' => 'Umbingeleli obhinqileyo',
        'pt' => 'Sacerdotisa',
        'st' => 'Moprista oa basali'
    ],
    'member' => [
        'af' => 'Lidmaat',
        'en' => 'Member',
        'zu' => 'Ilunga',
        'xh' => 'Ilungu',
        'pt' => 'Membro',
        'st' => 'Setho'
    ],
    'apostle_sister' => [
        'af' => 'Apostel Suster',
        'en' => 'Apostle Sister',
        'zu' => 'Udade woMphostoli',
        'xh' => 'Udade woMpostile',
        'pt' => 'Irmã Apóstola',
        'st' => 'Ausi oa Moapostola'
    ],
    'overseer_sister' => [
        'af' => 'Opsiener Suster',
        'en' => 'Overseer Sister',
        'zu' => 'Udade woMbonisi',
        'xh' => 'Udade woMbonisi',
        'pt' => 'Irmã Supervisora',
        'st' => 'Ausi oa Mooki'
    ],
    'evangelist_sister' => [
        'af' => 'Evangelis Suster',
        'en' => 'Evangelist Sister',
        'zu' => 'Udade woMvangeli',
        'xh' => 'Udade woMvangeli',
        'pt' => 'Irmã Evangelista',
        'st' => 'Ausi oa Moevangeli'
    ],
    'prophet_sister' => [
        'af' => 'Profeet Suster',
        'en' => 'Prophet Sister',
        'zu' => 'Udade woMprofethi',
        'xh' => 'Udade woMprofeti',
        'pt' => 'Irmã Profetisa',
        'st' => 'Ausi oa Moporofeta'
    ],
    'elder_sister' => [
        'af' => 'Oudste Suster',
        'en' => 'Elder Sister',
        'zu' => 'Udade woMdala',
        'xh' => 'Udade woMdala',
        'pt' => 'Irmã Anciã',
        'st' => 'Ausi oa Moholo'
    ],
    'priest_sister' => [
        'af' => 'Priester Suster',
        'en' => 'Priest Sister',
        'zu' => 'Udade woMpristi',
        'xh' => 'Udade woMbingeleli',
        'pt' => 'Irmã Sacerdotisa',
        'st' => 'Ausi oa Moprista'
    ],
    'subdeacon' => [
        'af' => 'Onderdiaken',
        'en' => 'Underdeacon',
        'zu' => 'Umdiakoni omncane',
        'xh' => 'Umdiakoni omncinane',
        'pt' => 'Subdiácono',
        'st' => 'Modiakone e monyenyane'
    ],
    'subdeacon_sister' => [
        'af' => 'Onderdiaken Suster',
        'en' => 'Underdeacon Sister',
        'zu' => 'Udade woMdiakoni omncane',
        'xh' => 'Udade woMdiakoni omncinane',
        'pt' => 'Irmã Subdiaconisa',
        'st' => 'Ausi oa Modiakone e monyenyane'
    ],
    'choir_leader_brother' => [
        'af' => 'Koorleier Broer',
        'en' => 'Choir Leader Brother',
        'zu' => 'Umfowethu oHola iKwaya',
        'xh' => 'Umzalwana oKhokela iKwayara',
        'pt' => 'Irmão Regente do Coro',
        'st' => 'Moetapele oa Mokhotlo'
    ],
    'choir_leader_sister' => [
        'af' => 'Koorleier Suster',
        'en' => 'Choir Leader Sister',
        'zu' => 'Udade oHola iKwaya',
        'xh' => 'Udade oKhokela iKwayara',
        'pt' => 'Irmã Regente do Coro',
        'st' => 'Ausi oa Moetapele oa Mokhotlo'
    ],
    'helper' => [
        'af' => 'Hulpkrag',
        'en' => 'Helper',
        'zu' => 'Umsizi',
        'xh' => 'Umncedisi',
        'pt' => 'Ajudante',
        'st' => 'Mothusi'
    ],
    'helper_sister' => [
        'af' => 'Hulpkrag Suster',
        'en' => 'Helper Sister',
        'zu' => 'Udade oSizayo',
        'xh' => 'Udade oNcedisayo',
        'pt' => 'Irmã Ajudante',
        'st' => 'Ausi oa Mothusi'
    ],
    'brother' => [
        'af' => 'Broer',
        'en' => 'Brother',
        'zu' => 'Umfowethu',
        'xh' => 'Umzalwana',
        'pt' => 'Irmão',
        'st' => 'Moena'
    ],
    'sister' => [
        'af' => 'Suster',
        'en' => 'Sister',
        'zu' => 'Udade',
        'xh' => 'Udade',
        'pt' => 'Irmã',
        'st' => 'Ausi'
    ],

    // ==================== COMMON ACTIONS ====================
    'delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Excluir',
        'st' => 'Hlakola'
    ],
    'edit' => [
        'af' => 'Wysig',
        'en' => 'Edit',
        'zu' => 'Hlela',
        'xh' => 'Hlela',
        'pt' => 'Editar',
        'st' => 'Fetola'
    ],
    'view' => [
        'af' => 'Bekyk',
        'en' => 'View',
        'zu' => 'Buka',
        'xh' => 'Jonga',
        'pt' => 'Ver',
        'st' => 'Sheba'
    ],
    'search' => [
        'af' => 'Soek',
        'en' => 'Search',
        'zu' => 'Sesha',
        'xh' => 'Khangela',
        'pt' => 'Pesquisar',
        'st' => 'Batla'
    ],
    'add' => [
        'af' => 'Voeg by',
        'en' => 'Add',
        'zu' => 'Engeza',
        'xh' => 'Yongeza',
        'pt' => 'Adicionar',
        'st' => 'Eketsa'
    ],
    'confirm' => [
        'af' => 'Bevestig',
        'en' => 'Confirm',
        'zu' => 'Qinisekisa',
        'xh' => 'Qinisekisa',
        'pt' => 'Confirmar',
        'st' => 'Netefatsa'
    ],
    'approve' => [
        'af' => 'Goedkeur',
        'en' => 'Approve',
        'zu' => 'Vuma',
        'xh' => 'Vuma',
        'pt' => 'Aprovar',
        'st' => 'Amohela'
    ],
    'loading' => [
        'af' => 'Laai...',
        'en' => 'Loading...',
        'zu' => 'Iyalayisha...',
        'xh' => 'Iyalayisha...',
        'pt' => 'Carregando...',
        'st' => 'E kenya...'
    ],
    'no_results' => [
        'af' => 'Geen resultate nie',
        'en' => 'No results',
        'zu' => 'Akukho miphumela',
        'xh' => 'Akukho ziphumo',
        'pt' => 'Sem resultados',
        'st' => 'Ha ho liphetho'
    ],
    'success' => [
        'af' => 'Suksesvol',
        'en' => 'Success',
        'zu' => 'Impumelelo',
        'xh' => 'Impumelelo',
        'pt' => 'Sucesso',
        'st' => 'Katleho'
    ],
    'failed' => [
        'af' => 'Misluk',
        'en' => 'Failed',
        'zu' => 'Kwehlulekile',
        'xh' => 'Ayiphumelelanga',
        'pt' => 'Falhou',
        'st' => 'E hlolehile'
    ],

    // ==================== ELDERS / TEACHING ====================
    'edit_teaching' => [
        'af' => 'Wysig Lering',
        'en' => 'Edit Teaching',
        'zu' => 'Hlela Imfundiso',
        'xh' => 'Hlela Imfundiso',
        'pt' => 'Editar Ensino',
        'st' => 'Fetola Thuto'
    ],
    'monthly_teaching' => [
        'af' => 'Maandelikse Lering',
        'en' => 'Monthly Teaching',
        'zu' => 'Imfundiso Yenyanga',
        'xh' => 'Imfundiso Yenyanga',
        'pt' => 'Ensino Mensal',
        'st' => 'Thuto ea Khoeli'
    ],
    'font' => [
        'af' => 'Lettertipe',
        'en' => 'Font',
        'zu' => 'Uhlobo Lwesibhalo',
        'xh' => 'Uhlobo Lwesibhalo',
        'pt' => 'Fonte',
        'st' => 'Mofuta oa Mongolo'
    ],
    'size' => [
        'af' => 'Grootte',
        'en' => 'Size',
        'zu' => 'Usayizi',
        'xh' => 'Ubungakanani',
        'pt' => 'Tamanho',
        'st' => 'Boholo'
    ],
    'color' => [
        'af' => 'Kleur',
        'en' => 'Color',
        'zu' => 'Umbala',
        'xh' => 'Umbala',
        'pt' => 'Cor',
        'st' => 'Mmala'
    ],
    'add_verse' => [
        'af' => 'Voeg Vers By',
        'en' => 'Add Verse',
        'zu' => 'Engeza Ivesi',
        'xh' => 'Yongeza Ivesi',
        'pt' => 'Adicionar Versículo',
        'st' => 'Eketsa Temana'
    ],
    'improve' => [
        'af' => 'Verbeter',
        'en' => 'Improve',
        'zu' => 'Thuthukisa',
        'xh' => 'Phucula',
        'pt' => 'Melhorar',
        'st' => 'Ntlafatsa'
    ],
    'translate' => [
        'af' => 'Vertaal',
        'en' => 'Translate',
        'zu' => 'Humusha',
        'xh' => 'Guqulela',
        'pt' => 'Traduzir',
        'st' => 'Fetolela'
    ],
    'translate_all' => [
        'af' => 'Vertaal Alles',
        'en' => 'Translate All',
        'zu' => 'Humusha Konke',
        'xh' => 'Guqulela Konke',
        'pt' => 'Traduzir Tudo',
        'st' => 'Fetolela Tsohle'
    ],
    'translating' => [
        'af' => 'Besig om te vertaal',
        'en' => 'Translating',
        'zu' => 'Ukuhumusha',
        'xh' => 'Ukuguqulela',
        'pt' => 'Traduzindo',
        'st' => 'E a fetolela'
    ],
    'do_not_close' => [
        'af' => 'Moenie hierdie venster toemaak terwyl dit vertaal nie.',
        'en' => 'Please do not close this window while translating.',
        'zu' => 'Sicela ungavali leli windi ngesikhathi kuhunyushwa.',
        'xh' => 'Nceda ungayivali le window ngethuba kuguqulelwa.',
        'pt' => 'Por favor, não feche esta janela durante a tradução.',
        'st' => 'Ka kopo se koale fensetere ena ha e ntse e fetolela.'
    ],
    'translation_time' => [
        'af' => 'Dit kan \'n paar minute neem...',
        'en' => 'This can take a few minutes...',
        'zu' => 'Lokhu kungathatha imizuzu embalwa...',
        'xh' => 'Oku kunokuthatha imizuzu embalwa...',
        'pt' => 'Isso pode levar alguns minutos...',
        'st' => 'Hona ho ka nka metsotso e seng mekae...'
    ],
    'ready_to_save' => [
        'af' => 'Gereed om te stoor',
        'en' => 'Ready to save',
        'zu' => 'Kulungele ukugcina',
        'xh' => 'Kulungele ukugcina',
        'pt' => 'Pronto para salvar',
        'st' => 'E lokile ho bolokoa'
    ],
    'save_all' => [
        'af' => 'Stoor Alles',
        'en' => 'Save All',
        'zu' => 'Gcina Konke',
        'xh' => 'Gcina Konke',
        'pt' => 'Salvar Tudo',
        'st' => 'Boloka Tsohle'
    ],
    'add_bible_verse' => [
        'af' => 'Voeg Bybelvers By',
        'en' => 'Add Bible Verse',
        'zu' => 'Engeza Ivesi LeBhayibheli',
        'xh' => 'Yongeza Ivesi LeBhayibhile',
        'pt' => 'Adicionar Versículo Bíblico',
        'st' => 'Eketsa Temana ea Bibele'
    ],
    'unsaved_changes' => [
        'af' => 'Ongestoorde veranderinge',
        'en' => 'Unsaved changes',
        'zu' => 'Izinguquko ezingagciniwe',
        'xh' => 'Iinguqu ezingagcinwanga',
        'pt' => 'Alterações não salvas',
        'st' => 'Liphetoho tse sa bolokoang'
    ],
    'saving' => [
        'af' => 'Stoor tans...',
        'en' => 'Saving...',
        'zu' => 'Iyagcina...',
        'xh' => 'Iyagcina...',
        'pt' => 'Salvando...',
        'st' => 'E a boloka...'
    ],
    'saved' => [
        'af' => 'Gestoor!',
        'en' => 'Saved!',
        'zu' => 'Kugciniwe!',
        'xh' => 'Igciniwe!',
        'pt' => 'Salvo!',
        'st' => 'E bolokiloe!'
    ],
    'save_failed' => [
        'af' => 'Stoor het misluk',
        'en' => 'Save failed',
        'zu' => 'Ukugcina kuhlulekile',
        'xh' => 'Ukugcina akuphumelelanga',
        'pt' => 'Falha ao salvar',
        'st' => 'Ho boloka ho hlolehile'
    ],
    'translate_confirm' => [
        'af' => 'Dit sal die inhoud van al die ander tale vervang met nuwe vertalings van hierdie blad. Alles word daarna outomaties gestoor. Gaan voort?',
        'en' => 'This will replace the content of all other languages with new translations from this tab. Everything will be saved automatically afterwards. Continue?',
        'zu' => 'Lokhu kuzothatha indawo yokuqukethwe kwazo zonke ezinye izilimi ngezinguqulo ezintsha ezivela kuleli thebhu. Konke kuzogcinwa ngokuzenzakalela ngemva kwalokho. Qhubeka?',
        'xh' => 'Oku kuya kutshintsha umxholo wazo zonke ezinye iilwimi ngeenguqulelo ezintsha ezivela kweli thebhu. Yonke into iya kugcinwa ngokuzenzekelayo emva koko. Qhubeka?',
        'pt' => 'Isso substituirá o conteúdo de todos os outros idiomas por novas traduções desta aba. Tudo será salvo automaticamente em seguida. Continuar?',
        'st' => 'Sena se tla nkela sebaka litaba tsa lipuo tse ling kaofela ka liphetolelo tse ncha tse tsoang lekhasing lena. Tsohle li tla bolokoa ka bo tsona ka mor\'a moo. Tsoela pele?'
    ],
    'translations_done_saved' => [
        'af' => 'Vertalings voltooi en gestoor!',
        'en' => 'Translations complete and saved!',
        'zu' => 'Ukuhumusha kuqediwe futhi kugciniwe!',
        'xh' => 'Uguqulelo lugqityiwe kwaye lugciniwe!',
        'pt' => 'Traduções concluídas e salvas!',
        'st' => 'Liphetolelo li phethiloe \'me li bolokiloe!'
    ],
    'improving' => [
        'af' => 'Verbeter tans...',
        'en' => 'Improving...',
        'zu' => 'Iyathuthukisa...',
        'xh' => 'Iyaphucula...',
        'pt' => 'Melhorando...',
        'st' => 'E a ntlafatsa...'
    ],
    'improved' => [
        'af' => 'Verbeter!',
        'en' => 'Improved!',
        'zu' => 'Kuthuthukisiwe!',
        'xh' => 'Iphuculiwe!',
        'pt' => 'Melhorado!',
        'st' => 'E ntlafalitsoe!'
    ],
    'verse_inserted' => [
        'af' => 'Vers ingevoeg!',
        'en' => 'Verse inserted!',
        'zu' => 'Ivesi lifakiwe!',
        'xh' => 'Ivesi ifakiwe!',
        'pt' => 'Versículo inserido!',
        'st' => 'Temana e kentsoe!'
    ],
    'bible_not_available' => [
        'af' => 'Die Bybel is nie in hierdie taal beskikbaar nie',
        'en' => 'The Bible is not available in this language',
        'zu' => 'IBhayibheli alitholakali ngalolu limi',
        'xh' => 'IBhayibhile ayifumaneki ngolu lwimi',
        'pt' => 'A Bíblia não está disponível neste idioma',
        'st' => 'Bibele ha e fumanehe ka puo ena'
    ],
    'select_all_fields' => [
        'af' => 'Kies asseblief al die velde',
        'en' => 'Please select all fields',
        'zu' => 'Sicela ukhethe zonke izinkambu',
        'xh' => 'Nceda ukhethe zonke iindawo',
        'pt' => 'Por favor, selecione todos os campos',
        'st' => 'Ka kopo khetha likarolo tsohle'
    ],
    'editor_empty' => [
        'af' => 'Die inhoud is leeg',
        'en' => 'The content is empty',
        'zu' => 'Okuqukethwe akunalutho',
        'xh' => 'Umxholo awunanto',
        'pt' => 'O conteúdo está vazio',
        'st' => 'Litaba ha li eo'
    ],
    'translation_stopped' => [
        'af' => 'Vertaling het gestop by',
        'en' => 'Translation stopped at',
        'zu' => 'Ukuhumusha kume ku',
        'xh' => 'Uguqulelo lumile ku',
        'pt' => 'A tradução parou em',
        'st' => 'Phetolelo e emisitse ho'
    ],
    'languages_completed' => [
        'af' => 'taal/tale suksesvol voltooi',
        'en' => 'language(s) completed successfully',
        'zu' => 'izilimi eziqedwe ngempumelelo',
        'xh' => 'iilwimi ezigqitywe ngempumelelo',
        'pt' => 'idioma(s) concluído(s) com sucesso',
        'st' => 'puo/lipuo tse phethiloeng ka katleho'
    ],
    'line_spacing' => [
        'af' => 'Spasiëring',
        'en' => 'Line spacing',
        'zu' => 'Isikhala semigqa',
        'xh' => 'Isithuba semigca',
        'pt' => 'Espaçamento',
        'st' => 'Sebaka sa mela'
    ],
    'text_color' => [
        'af' => 'Tekskleur',
        'en' => 'Text color',
        'zu' => 'Umbala wombhalo',
        'xh' => 'Umbala wombhalo',
        'pt' => 'Cor do texto',
        'st' => 'Mmala oa mongolo'
    ],
    'heading' => [
        'af' => 'Opskrif',
        'en' => 'Heading',
        'zu' => 'Isihloko',
        'xh' => 'Isihloko',
        'pt' => 'Título',
        'st' => 'Sehlooho'
    ],
    'paragraph' => [
        'af' => 'Paragraaf',
        'en' => 'Paragraph',
        'zu' => 'Indima',
        'xh' => 'Umhlathi',
        'pt' => 'Parágrafo',
        'st' => 'Serapa'
    ],
    'bold' => [
        'af' => 'Vetdruk',
        'en' => 'Bold',
        'zu' => 'Okugqamile',
        'xh' => 'Ngqindilili',
        'pt' => 'Negrito',
        'st' => 'Botenya'
    ],
    'italic' => [
        'af' => 'Kursief',
        'en' => 'Italic',
        'zu' => 'I-ithalikhi',
        'xh' => 'I-italiki',
        'pt' => 'Itálico',
        'st' => 'Italiki'
    ],
    'underline' => [
        'af' => 'Onderstreep',
        'en' => 'Underline',
        'zu' => 'Dwebela ngaphansi',
        'xh' => 'Krwela umgca ngezantsi',
        'pt' => 'Sublinhado',
        'st' => 'Mola ka tlase'
    ],
    'align_left' => [
        'af' => 'Links belyn',
        'en' => 'Align left',
        'zu' => 'Hlela kwesokunxele',
        'xh' => 'Lungelelanisa ekhohlo',
        'pt' => 'Alinhar à esquerda',
        'st' => 'Lekanya ka ho le letšehali'
    ],
    'align_center' => [
        'af' => 'Sentreer',
        'en' => 'Center',
        'zu' => 'Maphakathi',
        'xh' => 'Embindini',
        'pt' => 'Centralizar',
        'st' => 'Bohareng'
    ],
    'align_right' => [
        'af' => 'Regs belyn',
        'en' => 'Align right',
        'zu' => 'Hlela kwesokudla',
        'xh' => 'Lungelelanisa ekunene',
        'pt' => 'Alinhar à direita',
        'st' => 'Lekanya ka ho le letona'
    ],
    'bullet_list' => [
        'af' => 'Kolpuntlys',
        'en' => 'Bullet list',
        'zu' => 'Uhlu lwamabhulethi',
        'xh' => 'Uluhlu lweebhulethi',
        'pt' => 'Lista com marcadores',
        'st' => 'Lethathamo la matheba'
    ],
    'numbered_list' => [
        'af' => 'Genommerde lys',
        'en' => 'Numbered list',
        'zu' => 'Uhlu olunezinombolo',
        'xh' => 'Uluhlu olunamanani',
        'pt' => 'Lista numerada',
        'st' => 'Lethathamo le nang le linomoro'
    ],
    'undo' => [
        'af' => 'Ontdoen',
        'en' => 'Undo',
        'zu' => 'Hlehlisa',
        'xh' => 'Buyisela umva',
        'pt' => 'Desfazer',
        'st' => 'Khutlisa'
    ],
    'redo' => [
        'af' => 'Herdoen',
        'en' => 'Redo',
        'zu' => 'Phinda',
        'xh' => 'Yenza kwakhona',
        'pt' => 'Refazer',
        'st' => 'Etsa hape'
    ],
    'strikethrough' => [
        'af' => 'Deurhaal',
        'en' => 'Strikethrough',
        'zu' => 'Umugqa phakathi',
        'xh' => 'Umgca phakathi',
        'pt' => 'Tachado',
        'st' => 'Mola bohareng'
    ],
    'clear_formatting' => [
        'af' => 'Verwyder formatering',
        'en' => 'Clear formatting',
        'zu' => 'Susa ukufomatha',
        'xh' => 'Susa ulungiselelo',
        'pt' => 'Limpar formatação',
        'st' => 'Tlosa sebopeho'
    ],
    'align_justify' => [
        'af' => 'Volblok belyn',
        'en' => 'Justify',
        'zu' => 'Lungelelanisa zombili',
        'xh' => 'Lungelelanisa macala omabini',
        'pt' => 'Justificar',
        'st' => 'Lekanya mahlakoreng'
    ],
    'indent' => [
        'af' => 'Inkeep',
        'en' => 'Indent',
        'zu' => 'Sondeza phakathi',
        'xh' => 'Sondeza phakathi',
        'pt' => 'Aumentar recuo',
        'st' => 'Sutumetsa ka hare'
    ],
    'outdent' => [
        'af' => 'Uitkeep',
        'en' => 'Outdent',
        'zu' => 'Sondeza ngaphandle',
        'xh' => 'Sondeza ngaphandle',
        'pt' => 'Diminuir recuo',
        'st' => 'Sutumetsa ka ntle'
    ],
    'quote_block' => [
        'af' => 'Aanhaling',
        'en' => 'Quote',
        'zu' => 'Isicaphuno',
        'xh' => 'Ucaphulo',
        'pt' => 'Citação',
        'st' => 'Qotsulo'
    ],
    'divider' => [
        'af' => 'Skeidingslyn',
        'en' => 'Divider',
        'zu' => 'Umugqa wokwahlukanisa',
        'xh' => 'Umgca wokwahlula',
        'pt' => 'Linha divisória',
        'st' => 'Mola o arolang'
    ],
    'highlight_color' => [
        'af' => 'Merkkleur',
        'en' => 'Highlight',
        'zu' => 'Gqamisa',
        'xh' => 'Qaqambisa',
        'pt' => 'Realce',
        'st' => 'Totobatsa'
    ],
    'remove_highlight' => [
        'af' => 'Verwyder merk',
        'en' => 'Remove highlight',
        'zu' => 'Susa ukugqamisa',
        'xh' => 'Susa uqaqambiso',
        'pt' => 'Remover realce',
        'st' => 'Tlosa totobatso'
    ],
    'insert_link' => [
        'af' => 'Voeg skakel by',
        'en' => 'Insert link',
        'zu' => 'Faka isixhumanisi',
        'xh' => 'Faka ikhonkco',
        'pt' => 'Inserir link',
        'st' => 'Kenya sehokelo'
    ],
    'remove_link' => [
        'af' => 'Verwyder skakel',
        'en' => 'Remove link',
        'zu' => 'Susa isixhumanisi',
        'xh' => 'Susa ikhonkco',
        'pt' => 'Remover link',
        'st' => 'Tlosa sehokelo'
    ],
    'link_prompt' => [
        'af' => 'Voer die webadres in:',
        'en' => 'Enter the web address:',
        'zu' => 'Faka ikheli lewebhu:',
        'xh' => 'Faka idilesi yewebhu:',
        'pt' => 'Digite o endereço da web:',
        'st' => 'Kenya aterese ea webo:'
    ],
    'characters' => [
        'af' => 'karakters',
        'en' => 'characters',
        'zu' => 'izinhlamvu',
        'xh' => 'oonobumba',
        'pt' => 'caracteres',
        'st' => 'litlhaku'
    ],
    // Teaching editor - paste choice & draft recovery
    'paste_label' => [
        'af' => 'Plak',
        'en' => 'Paste',
        'zu' => 'Namathisela',
        'xh' => 'Ncamathisela',
        'pt' => 'Colar',
        'st' => 'Khomaretsa'
    ],
    'paste_rich' => [
        'af' => 'Met formaat',
        'en' => 'With formatting',
        'zu' => 'Nokufomatha',
        'xh' => 'Nolungiselelo',
        'pt' => 'Com formatação',
        'st' => 'Le sebopeho'
    ],
    'paste_plain' => [
        'af' => 'Gewone teks',
        'en' => 'Plain text',
        'zu' => 'Umbhalo kuphela',
        'xh' => 'Umbhalo kuphela',
        'pt' => 'Texto simples',
        'st' => 'Mongolo feela'
    ],
    'paste_rich_hint' => [
        'af' => 'Hou die voorkoms van die bron: vet, skuins, opskrifte, lyste, kleure en tabelle',
        'en' => 'Keep the look of the source: bold, italic, headings, lists, colours and tables',
        'zu' => 'Gcina ukubukeka komthombo: okugqamile, okutshekile, izihloko, uhlu, imibala namathebula',
        'xh' => 'Gcina inkangeleko yomthombo: ngqindilili, thambeka, izihloko, uluhlu, imibala neetafile',
        'pt' => 'Manter a aparência da origem: negrito, itálico, títulos, listas, cores e tabelas',
        'st' => 'Boloka ponahalo ea mohloli: e teteaneng, e sekameng, lihlooho, manane, mebala le litafole'
    ],
    'paste_plain_hint' => [
        'af' => 'Plak net die teks en kies self die formaat (Ctrl+Shift+V plak altyd as gewone teks)',
        'en' => 'Paste only the text and choose the formatting yourself (Ctrl+Shift+V always pastes plain text)',
        'zu' => 'Namathisela umbhalo kuphela bese uzikhethela ukufomatha (Ctrl+Shift+V uhlala unamathisela umbhalo kuphela)',
        'xh' => 'Ncamathisela umbhalo kuphela uze uzikhethele ulungiselelo (Ctrl+Shift+V ihlala incamathisela umbhalo kuphela)',
        'pt' => 'Colar apenas o texto e escolher a formatação você mesmo (Ctrl+Shift+V cola sempre texto simples)',
        'st' => 'Khomaretsa mongolo feela ebe u ikhethela sebopeho (Ctrl+Shift+V kamehla e khomaretsa mongolo feela)'
    ],
    'paste_options' => [
        'af' => 'Geplak as',
        'en' => 'Pasted as',
        'zu' => 'Kunamathiselwe njenge',
        'xh' => 'Kuncamathiselwe njenge',
        'pt' => 'Colado como',
        'st' => 'E khomaretsoe e le'
    ],
    'paste_no_images' => [
        'af' => 'Prente kan nie hier geplak word nie',
        'en' => 'Images cannot be pasted here',
        'zu' => 'Izithombe azikwazi ukunamathiselwa lapha',
        'xh' => 'Imifanekiso ayinakuncamathiselwa apha',
        'pt' => 'Não é possível colar imagens aqui',
        'st' => 'Litšoantšo li ke ke tsa khomaretsoa mona'
    ],
    'default_color' => [
        'af' => 'Gewone kleur',
        'en' => 'Default colour',
        'zu' => 'Umbala ojwayelekile',
        'xh' => 'Umbala oqhelekileyo',
        'pt' => 'Cor padrão',
        'st' => 'Mmala o tloaelehileng'
    ],
    'editor_keys_hint' => [
        'af' => 'Tab keep in. Druk Esc en dan Tab om die teks te verlaat.',
        'en' => 'Tab indents. Press Esc, then Tab, to leave the text.',
        'zu' => 'U-Tab uyangenisa. Cindezela u-Esc bese u-Tab ukuze uphume embhalweni.',
        'xh' => 'I-Tab iyangenisa. Cofa u-Esc, emva koko i-Tab, ukuze uphume kumbhalo.',
        'pt' => 'Tab aumenta o recuo. Pressione Esc e depois Tab para sair do texto.',
        'st' => 'Tab e kenya ka hare. Tobetsa Esc ebe Tab ho tsoa mongolong.'
    ],
    'draft_found' => [
        'af' => "Daar is 'n ongestoorde konsep van hierdie lering",
        'en' => 'There is an unsaved draft of this teaching',
        'zu' => 'Kukhona uhlaka olungagciniwe lwale mfundiso',
        'xh' => 'Kukho uyilo olungagcinwanga lwale mfundiso',
        'pt' => 'Existe um rascunho não salvo deste ensino',
        'st' => 'Ho na le moralo o sa bolokoang oa thuto ena'
    ],
    'restore_draft' => [
        'af' => 'Herstel',
        'en' => 'Restore',
        'zu' => 'Buyisela',
        'xh' => 'Buyisela',
        'pt' => 'Restaurar',
        'st' => 'Khutlisa'
    ],
    'discard_draft' => [
        'af' => 'Gooi weg',
        'en' => 'Discard',
        'zu' => 'Lahla',
        'xh' => 'Lahla',
        'pt' => 'Descartar',
        'st' => 'Lahla'
    ],
    'book' => [
        'af' => 'Boek',
        'en' => 'Book',
        'zu' => 'Incwadi',
        'xh' => 'Incwadi',
        'pt' => 'Livro',
        'st' => 'Buka'
    ],
    'chapter' => [
        'af' => 'Hoofstuk',
        'en' => 'Chapter',
        'zu' => 'Isahluko',
        'xh' => 'Isahluko',
        'pt' => 'Capítulo',
        'st' => 'Khaolo'
    ],
    'from' => [
        'af' => 'Van',
        'en' => 'From',
        'zu' => 'Kusuka',
        'xh' => 'Ukusuka',
        'pt' => 'De',
        'st' => 'Ho tloha'
    ],
    'to' => [
        'af' => 'Tot',
        'en' => 'To',
        'zu' => 'Kuya',
        'xh' => 'Ukuya',
        'pt' => 'Até',
        'st' => 'Ho fihlela'
    ],
    'insert' => [
        'af' => 'Voeg In',
        'en' => 'Insert',
        'zu' => 'Faka',
        'xh' => 'Faka',
        'pt' => 'Inserir',
        'st' => 'Kenya'
    ],
    'my_profile' => [
        'af' => 'My Profiel',
        'en' => 'My Profile',
        'zu' => 'Iphrofayela Yami',
        'xh' => 'Iprofayile Yam',
        'pt' => 'Meu Perfil',
        'st' => 'Profaele ea Ka'
    ],

    // ==================== ACCOUNTS & SETTINGS ====================
    'accounts_billing' => [
        'af' => 'Rekeninge & Betalings',
        'en' => 'Accounts & Billing',
        'zu' => 'Ama-akhawunti & Ukukhokhela',
        'xh' => 'Iiakhawunti & Ukuhlawula',
        'pt' => 'Contas e Faturamento',
        'st' => 'Li-akhaonto le Litefo'
    ],
    'no_billing_history' => [
        'af' => 'Geen betalingsgeskiedenis beskikbaar nie.',
        'en' => 'No billing history available.',
        'zu' => 'Akukho mlando wokukhokha otholakalayo.',
        'xh' => 'Akukho mbali yokuhlawula efumanekayo.',
        'pt' => 'Nenhum histórico de faturamento disponível.',
        'st' => 'Ha ho na nalane ea litefo e fumanehang.'
    ],
    'current_password' => [
        'af' => 'Huidige Wagwoord',
        'en' => 'Current Password',
        'zu' => 'Iphasiwedi Yamanje',
        'xh' => 'Iphasiwedi Yangoku',
        'pt' => 'Senha Atual',
        'st' => 'Phasewete ea Joale'
    ],
    'new_password' => [
        'af' => 'Nuwe Wagwoord',
        'en' => 'New Password',
        'zu' => 'Iphasiwedi Entsha',
        'xh' => 'Iphasiwedi Entsha',
        'pt' => 'Nova Senha',
        'st' => 'Phasewete e Ncha'
    ],
    'confirm_password' => [
        'af' => 'Bevestig Wagwoord',
        'en' => 'Confirm Password',
        'zu' => 'Qinisekisa Iphasiwedi',
        'xh' => 'Qinisekisa Iphasiwedi',
        'pt' => 'Confirmar Senha',
        'st' => 'Netefatsa Phasewete'
    ],
    'change_password' => [
        'af' => 'Verander Wagwoord',
        'en' => 'Change Password',
        'zu' => 'Shintsha Iphasiwedi',
        'xh' => 'Tshintsha Iphasiwedi',
        'pt' => 'Alterar Senha',
        'st' => 'Fetola Phasewete'
    ],
    'passwords_no_match' => [
        'af' => 'Wagwoorde stem nie ooreen nie',
        'en' => 'Passwords do not match',
        'zu' => 'Amaphasiwedi awafani',
        'xh' => 'Iiphasiwedi azifani',
        'pt' => 'As senhas não correspondem',
        'st' => 'Liphasewete ha li tšoane'
    ],
    'password_min_length' => [
        'af' => 'Wagwoord moet minstens 6 karakters wees',
        'en' => 'Password must be at least 6 characters',
        'zu' => 'Iphasiwedi kufanele ibe okungenani izinhlamvu ezingu-6',
        'xh' => 'Iphasiwedi kufuneka ibe ubuncinane iinimizi ezintandathu',
        'pt' => 'A senha deve ter pelo menos 6 caracteres',
        'st' => 'Phasewete e tlameha ho ba le litlhaku tse 6 bonyane'
    ],
    'password_changed' => [
        'af' => 'Wagwoord suksesvol verander',
        'en' => 'Password changed successfully',
        'zu' => 'Iphasiwedi ishintshwe ngempumelelo',
        'xh' => 'Iphasiwedi itshintshwe ngempumelelo',
        'pt' => 'Senha alterada com sucesso',
        'st' => 'Phasewete e fetotsoe ka katleho'
    ],
    'password_change_failed' => [
        'af' => 'Kon nie wagwoord verander nie',
        'en' => 'Could not change password',
        'zu' => 'Ayikwazanga ukushintsha iphasiwedi',
        'xh' => 'Ayikwazanga ukutshintsha iphasiwedi',
        'pt' => 'Não foi possível alterar a senha',
        'st' => 'Ho hlolehile ho fetola phasewete'
    ],
    'network_error' => [
        'af' => 'Netwerkfout',
        'en' => 'Network error',
        'zu' => 'Iphutha lenethiwekhi',
        'xh' => 'Impazamo yenethiwekhi',
        'pt' => 'Erro de rede',
        'st' => 'Phoso ea marang-rang'
    ],

    // ==================== APPROVALS TAB ====================
    'user_approvals' => [
        'af' => 'Gebruiker Goedkeurings',
        'en' => 'User Approvals',
        'zu' => 'Izimvume Zabasebenzisi',
        'xh' => 'Imvume Yabasebenzisi',
        'pt' => 'Aprovações de Usuários',
        'st' => 'Litumello tsa Basebelisi'
    ],
    'no_pending_approvals' => [
        'af' => 'Geen hangende gebruiker goedkeurings nie.',
        'en' => 'No pending user approvals.',
        'zu' => 'Azikho izimvume zabasebenzisi ezilindile.',
        'xh' => 'Akukho mvume yabasebenzisi elindileyo.',
        'pt' => 'Nenhuma aprovação de usuário pendente.',
        'st' => 'Ha ho na litumello tsa basebelisi tse emetseng.'
    ],
    'role' => [
        'af' => 'Amp',
        'en' => 'Role',
        'zu' => 'Isikhundla',
        'xh' => 'Isikhundla',
        'pt' => 'Cargo',
        'st' => 'Boemo'
    ],
    'actions' => [
        'af' => 'Aksies',
        'en' => 'Actions',
        'zu' => 'Izenzo',
        'xh' => 'Izenzo',
        'pt' => 'Ações',
        'st' => 'Liketso'
    ],
    'spouse_requests' => [
        'af' => 'Eggenoot Versoeke',
        'en' => 'Spouse Requests',
        'zu' => 'Izicelo Zomlingani',
        'xh' => 'Izicelo Zomlingani',
        'pt' => 'Pedidos de Cônjuge',
        'st' => 'Likopo tsa Molekane'
    ],
    'no_pending_spouse' => [
        'af' => 'Geen hangende eggenoot versoeke nie.',
        'en' => 'No pending spouse requests.',
        'zu' => 'Azikho izicelo zomlingani ezilindile.',
        'xh' => 'Akukho zicelo zomlingani ezilindileyo.',
        'pt' => 'Nenhum pedido de cônjuge pendente.',
        'st' => 'Ha ho na likopo tsa molekane tse emetseng.'
    ],
    'requester' => [
        'af' => 'Versoeker',
        'en' => 'Requester',
        'zu' => 'Umceli',
        'xh' => 'Umceli',
        'pt' => 'Solicitante',
        'st' => 'Mokopi'
    ],
    'receiver' => [
        'af' => 'Ontvanger',
        'en' => 'Receiver',
        'zu' => 'Umamukeli',
        'xh' => 'Umamkeli',
        'pt' => 'Destinatário',
        'st' => 'Moamoheli'
    ],
    'status' => [
        'af' => 'Status',
        'en' => 'Status',
        'zu' => 'Isimo',
        'xh' => 'Isimo',
        'pt' => 'Status',
        'st' => 'Boemo'
    ],
    'pending' => [
        'af' => 'Hangende',
        'en' => 'Pending',
        'zu' => 'Iyalinda',
        'xh' => 'Iyalinda',
        'pt' => 'Pendente',
        'st' => 'E emetse'
    ],
    'spouse_auto_approve_note' => [
        'af' => 'Eggenoot versoeke word gekoppel sodra die ontvanger aanvaar. As ampsdraer kan jy dit ook hier goedkeur of afwys.',
        'en' => 'Spouse requests are linked once the receiver accepts. As an office bearer you can also approve or reject them here.',
        'zu' => 'Izicelo zomlingani ziyaxhunywa uma umamukeli evuma. Njengomphathi ungazivuma noma uzinqabe lapha.',
        'xh' => 'Izicelo zomlingani ziyadityaniswa xa umamkeli evuma. Njengomphathi ungazivuma okanye uzale apha.',
        'pt' => 'Os pedidos de cônjuge são vinculados quando o destinatário aceita. Como portador de ofício, você também pode aprová-los ou rejeitá-los aqui.',
        'st' => 'Likopo tsa molekane li hokahanngoa ha moamoheli a amohela. Joaloka mo-ofisiri u ka boela ua li amohela kapa ua li hana mona.'
    ],
    'linked_spouses' => [
        'af' => 'Gekoppelde Huweliksmaats',
        'en' => 'Linked Spouses',
        'zu' => 'Abalingani Abaxhunyiwe',
        'xh' => 'Abalingani Abadityanisiweyo',
        'pt' => 'Cônjuges Vinculados',
        'st' => 'Balekane ba Hokahantsoeng'
    ],
    'no_linked_spouses' => [
        'af' => 'Geen gekoppelde huweliksmaats in jou omvang nie.',
        'en' => 'No linked spouses in your scope.',
        'zu' => 'Abekho abalingani abaxhunyiwe endaweni yakho.',
        'xh' => 'Abekho abalingani abadityanisiweyo kummandla wakho.',
        'pt' => 'Nenhum cônjuge vinculado no seu escopo.',
        'st' => 'Ha ho balekane ba hokahantsoeng sebakeng sa hao.'
    ],
    'unlink' => [
        'af' => 'Ontkoppel',
        'en' => 'Unlink',
        'zu' => 'Hlukanisa',
        'xh' => 'Yahlula',
        'pt' => 'Desvincular',
        'st' => 'Arola'
    ],
    'confirm_unlink_spouse' => [
        'af' => 'Is jy seker jy wil hierdie huweliksmaats ontkoppel? Hulle kan later weer gekoppel word.',
        'en' => 'Are you sure you want to unlink these spouses? They can be linked again later.',
        'zu' => 'Uqinisekile ukuthi ufuna ukuhlukanisa laba balingani? Bangaxhunywa futhi kamuva.',
        'xh' => 'Uqinisekile ufuna ukwahlula aba balingani? Bangadityaniswa kwakhona kamva.',
        'pt' => 'Tem certeza de que deseja desvincular estes cônjuges? Eles podem ser vinculados novamente mais tarde.',
        'st' => 'Na u na le bonnete ba hore u batla ho arola balekane bana? Ba ka hokahanngoa hape hamorao.'
    ],
    'notif_spouse_unlinked' => [
        'af' => 'Eggenoot Ontkoppel',
        'en' => 'Spouse Unlinked',
        'zu' => 'Umlingani Uhlukanisiwe',
        'xh' => 'Umlingani Wahlulwe',
        'pt' => 'Cônjuge Desvinculado',
        'st' => 'Molekane o Arotsoe'
    ],
    'notif_spouse_unlinked_msg' => [
        'af' => 'Jou eggenoot-koppeling met {name} is deur \'n ampsdraer verwyder.',
        'en' => 'Your spouse link with {name} was removed by an office bearer.',
        'zu' => 'Ukuxhunywa kwakho nomlingani u-{name} kususwe umphathi.',
        'xh' => 'Unxibelelwano lwakho nomlingani u-{name} lususwe ngumphathi.',
        'pt' => 'Seu vínculo de cônjuge com {name} foi removido por um portador de ofício.',
        'st' => 'Khokahano ea hao ea molekane le {name} e tlositsoe ke mo-ofisiri.'
    ],

    // ==================== TEACHING TAB ====================
    'teaching_management' => [
        'af' => 'Lering Bestuur',
        'en' => 'Teaching Management',
        'zu' => 'Ukuphathwa Kwemfundiso',
        'xh' => 'Ulawulo Lwemfundiso',
        'pt' => 'Gerenciamento de Ensino',
        'st' => 'Tsamaiso ea Thuto'
    ],
    'edit_teaching_desc' => [
        'af' => 'Wysig die maandelikse lering vir jou dorp en gemeente.',
        'en' => 'Edit the monthly teaching for your town and congregation.',
        'zu' => 'Hlela imfundiso yenyanga yedolobha lakho nebandla.',
        'xh' => 'Hlela imfundiso yenyanga yedolophu kunye nebandla lakho.',
        'pt' => 'Edite o ensino mensal para sua cidade e congregação.',
        'st' => 'Fetola thuto ea khoeli bakeng sa toropo le phuthego ea hao.'
    ],

    // ==================== AMPTES TAB ====================
    'no_access' => [
        'af' => 'Jy het nie toegang tot hierdie blad nie.',
        'en' => 'You do not have access to this page.',
        'zu' => 'Awunayo imvume yokungena kuleli khasi.',
        'xh' => 'Awunayo imvume yokungena kweli phepha.',
        'pt' => 'Você não tem acesso a esta página.',
        'st' => 'Ha o na tokelo ea ho kena leqepheng lena.'
    ],
    'search_offices' => [
        'af' => 'Soek ampte...',
        'en' => 'Search officers...',
        'zu' => 'Sesha izikhundla...',
        'xh' => 'Khangela izikhundla...',
        'pt' => 'Pesquisar cargos...',
        'st' => 'Batla likantoro...'
    ],
    'no_office_holders' => [
        'af' => 'Geen ampte gevind nie.',
        'en' => 'No officers found.',
        'zu' => 'Akukho zikhundla ezitholiwe.',
        'xh' => 'Akukho zikhundla zifunyenweyo.',
        'pt' => 'Nenhum titular de cargo encontrado.',
        'st' => 'Ha ho na batshuarulli ba likantoro ba fumanoeng.'
    ],
    'view_calendar' => [
        'af' => 'Kyk Kalender',
        'en' => 'View Calendar',
        'zu' => 'Buka Ikhalenda',
        'xh' => 'Jonga Ikhalenda',
        'pt' => 'Ver Calendário',
        'st' => 'Sheba Khalendara'
    ],
    'change_role' => [
        'af' => 'Verander Amp',
        'en' => 'Change Role',
        'zu' => 'Shintsha Isikhundla',
        'xh' => 'Tshintsha Isikhundla',
        'pt' => 'Alterar Cargo',
        'st' => 'Fetola Boemo'
    ],
    'user' => [
        'af' => 'Gebruiker',
        'en' => 'User',
        'zu' => 'Umsebenzisi',
        'xh' => 'Umsebenzisi',
        'pt' => 'Usuário',
        'st' => 'Mosebelisi'
    ],
    'new_role' => [
        'af' => 'Nuwe Amp',
        'en' => 'New Role',
        'zu' => 'Isikhundla Esisha',
        'xh' => 'Isikhundla Esitsha',
        'pt' => 'Novo Cargo',
        'st' => 'Boemo bo Botjha'
    ],
    'select_option' => [
        'af' => 'Kies...',
        'en' => 'Select...',
        'zu' => 'Khetha...',
        'xh' => 'Khetha...',
        'pt' => 'Selecionar...',
        'st' => 'Khetha...'
    ],

    // ==================== AFSPRAKE TAB ====================
    'pending_requests' => [
        'af' => 'Hangende Versoeke',
        'en' => 'Pending Requests',
        'zu' => 'Izicelo Ezilindile',
        'xh' => 'Izicelo Ezilindileyo',
        'pt' => 'Solicitações Pendentes',
        'st' => 'Likopo tse Emetseng'
    ],
    'wants_appointment' => [
        'af' => 'wil \'n afspraak maak',
        'en' => 'wants to make an appointment',
        'zu' => 'ufuna ukwenza isivumelwano',
        'xh' => 'ufuna ukwenza isivumelwano',
        'pt' => 'quer marcar um compromisso',
        'st' => 'o batla ho etsa kopano'
    ],
    'notes' => [
        'af' => 'Notas',
        'en' => 'Notes',
        'zu' => 'Amanothi',
        'xh' => 'Amanqaku',
        'pt' => 'Notas',
        'st' => 'Lintlha'
    ],
    'make_appointment' => [
        'af' => 'Maak Afspraak',
        'en' => 'Make Appointment',
        'zu' => 'Yenza Isivumelwano',
        'xh' => 'Yenza Isivumelwano',
        'pt' => 'Marcar Compromisso',
        'st' => 'Etsa Kopano'
    ],
    'with' => [
        'af' => 'Met',
        'en' => 'With',
        'zu' => 'No',
        'xh' => 'No',
        'pt' => 'Com',
        'st' => 'Le'
    ],
    'date' => [
        'af' => 'Datum',
        'en' => 'Date',
        'zu' => 'Usuku',
        'xh' => 'Umhla',
        'pt' => 'Data',
        'st' => 'Letsatsi'
    ],
    'time' => [
        'af' => 'Tyd',
        'en' => 'Time',
        'zu' => 'Isikhathi',
        'xh' => 'Ixesha',
        'pt' => 'Hora',
        'st' => 'Nako'
    ],
    'location' => [
        'af' => 'Plek',
        'en' => 'Location',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local',
        'st' => 'Sebaka'
    ],
    'send_request' => [
        'af' => 'Stuur Versoek',
        'en' => 'Send Request',
        'zu' => 'Thumela Isicelo',
        'xh' => 'Thumela Isicelo',
        'pt' => 'Enviar Solicitação',
        'st' => 'Romela Kopo'
    ],

    // ==================== BIBLE ====================
    'bible_reader' => [
        'af' => 'Bybel Leser',
        'en' => 'Bible Reader',
        'zu' => 'Umfundi WeBhayibheli',
        'xh' => 'Umfundi WeBhayibhile',
        'pt' => 'Leitor da Bíblia',
        'st' => 'Mobali oa Bibele'
    ],
    'quick_navigation' => [
        'af' => 'Vinnige Navigasie',
        'en' => 'Quick Navigation',
        'zu' => 'Ukuzulazula Okusheshayo',
        'xh' => 'Ukuhamba Okukhawulezileyo',
        'pt' => 'Navegação Rápida',
        'st' => 'Ho Tsamaea ka Potlako'
    ],
    'choose_testament' => [
        'af' => 'Kies Testament',
        'en' => 'Choose Testament',
        'zu' => 'Khetha Itestamente',
        'xh' => 'Khetha Itestamente',
        'pt' => 'Escolha Testamento',
        'st' => 'Khetha Testamente'
    ],
    'old_testament' => [
        'af' => 'Ou Testament',
        'en' => 'Old Testament',
        'zu' => 'Itestamente Elidala',
        'xh' => 'Itestamente Endala',
        'pt' => 'Antigo Testamento',
        'st' => 'Testamente ea Khale'
    ],
    'new_testament' => [
        'af' => 'Nuwe Testament',
        'en' => 'New Testament',
        'zu' => 'Itestamente Elisha',
        'xh' => 'Itestamente Entsha',
        'pt' => 'Novo Testamento',
        'st' => 'Testamente e Ncha'
    ],
    'genesis_malachi' => [
        'af' => 'Genesis - Maleági',
        'en' => 'Genesis - Malachi',
        'zu' => 'UGenesisi - UMalaki',
        'xh' => 'IGenesis - UMalaki',
        'pt' => 'Gênesis - Malaquias',
        'st' => 'Genese - Malaki'
    ],
    'matthew_revelation' => [
        'af' => 'Matthéüs - Openbaring',
        'en' => 'Matthew - Revelation',
        'zu' => 'UMathewu - ISambulo',
        'xh' => 'UMateyu - ISityhilelo',
        'pt' => 'Mateus - Apocalipse',
        'st' => 'Mattheu - Tšenolo'
    ],
    'back' => [
        'af' => 'Terug',
        'en' => 'Back',
        'zu' => 'Emuva',
        'xh' => 'Emva',
        'pt' => 'Voltar',
        'st' => 'Morao'
    ],
    'choose_book' => [
        'af' => 'Kies Boek',
        'en' => 'Choose Book',
        'zu' => 'Khetha Incwadi',
        'xh' => 'Khetha Incwadi',
        'pt' => 'Escolha Livro',
        'st' => 'Khetha Buka'
    ],
    'choose_chapter' => [
        'af' => 'Kies Hoofstuk',
        'en' => 'Choose Chapter',
        'zu' => 'Khetha Isahluko',
        'xh' => 'Khetha Isahluko',
        'pt' => 'Escolha Capítulo',
        'st' => 'Khetha Khaolo'
    ],
    'search_bible' => [
        'af' => 'Soek in die Bybel',
        'en' => 'Search the Bible',
        'zu' => 'Sesha IBhayibheli',
        'xh' => 'Khangela IBhayibhile',
        'pt' => 'Pesquisar na Bíblia',
        'st' => 'Batla Bibeleng'
    ],
    'type_search_term' => [
        'af' => 'Soek woorde of tik verwysing (bv. Joh 3:16)…',
        'en' => 'Search words or type a reference (e.g. John 3:16)…',
        'zu' => 'Sesha amagama noma thayipha inkomba (isib. Joh 3:16)…',
        'xh' => 'Khangela amagama okanye chwetheza isalathi (umz. Joh 3:16)…',
        'pt' => 'Pesquise palavras ou digite referência (ex. Jo 3:16)…',
        'st' => 'Batla mantsoe kapa thaepa referense (mohl. Joh 3:16)…'
    ],
    'no_notes_yet' => [
        'af' => 'Geen notas nog nie. Klik op \'n vers om \'n nota by te voeg!',
        'en' => 'No notes yet. Click on a verse to add a note!',
        'zu' => 'Awukabi namanothi. Chofoza ivesi ukuze ungeze inothi!',
        'xh' => 'Akukho manqaku okwangoku. Cofa ivesi ukongeza inqaku!',
        'pt' => 'Nenhuma nota ainda. Clique em um versículo para adicionar uma nota!',
        'st' => 'Ha ho na lintlha hajoale. Tobetsa temana ho eketsa tlhaloso!'
    ],
    'write_note_here' => [
        'af' => 'Skryf jou nota hier...',
        'en' => 'Write your note here...',
        'zu' => 'Bhala inothi lakho lapha...',
        'xh' => 'Bhala inqaku lakho apha...',
        'pt' => 'Escreva sua nota aqui...',
        'st' => 'Ngola tlhaloso ea hao mona...'
    ],
    'bookmarks' => [
        'af' => 'Boekmerke',
        'en' => 'Bookmarks',
        'zu' => 'Izimaki Zebhuku',
        'xh' => 'Izimaki Zeencwadi',
        'pt' => 'Favoritos',
        'st' => 'Limaraka tsa Libuka'
    ],
    'no_bookmarks_yet' => [
        'af' => 'Geen boekmerke nog nie.',
        'en' => 'No bookmarks yet.',
        'zu' => 'Azikho izimaki zebhuku okwamanje.',
        'xh' => 'Akukho zimaki zeencwadi okwangoku.',
        'pt' => 'Nenhum favorito ainda.',
        'st' => 'Ha ho na limaraka tsa libuka hajoale.'
    ],
    'ai_commentary' => [
        'af' => 'AI Kommentaar',
        'en' => 'AI Commentary',
        'zu' => 'Ukuphawula kwe-AI',
        'xh' => 'Inkcazelo ye-AI',
        'pt' => 'Comentário IA',
        'st' => 'Tlhaloso ea AI'
    ],
    'select_verse_ai' => [
        'af' => 'Kies \'n vers en vra AI \'n vraag.',
        'en' => 'Select a verse and ask AI a question.',
        'zu' => 'Khetha ivesi bese ubuza i-AI umbuzo.',
        'xh' => 'Khetha ivesi uze ubuze i-AI umbuzo.',
        'pt' => 'Selecione um versículo e pergunte à IA.',
        'st' => 'Khetha temana \'me o botse AI potso.'
    ],
    'cross_references' => [
        'af' => 'Kruisverwysings',
        'en' => 'Cross References',
        'zu' => 'Izinkomba Ezihlanganisayo',
        'xh' => 'Izalathiso Ezidibeneyo',
        'pt' => 'Referências Cruzadas',
        'st' => 'Litšupiso tse Fapaneng'
    ],
    'select_verse_cross_ref' => [
        'af' => 'Kies \'n vers om kruisverwysings te sien.',
        'en' => 'Select a verse to see cross-references.',
        'zu' => 'Khetha ivesi ukubona izinkomba ezihlanganisayo.',
        'xh' => 'Khetha ivesi ukubona izalathiso ezidibeneyo.',
        'pt' => 'Selecione um versículo para ver referências cruzadas.',
        'st' => 'Khetha temana ho bona litšupiso tse fapaneng.'
    ],
    'choose_color' => [
        'af' => 'Kies Kleur',
        'en' => 'Choose Color',
        'zu' => 'Khetha Umbala',
        'xh' => 'Khetha Umbala',
        'pt' => 'Escolha Cor',
        'st' => 'Khetha Mmala'
    ],
    'pink' => [
        'af' => 'Pienk',
        'en' => 'Pink',
        'zu' => 'Okupinki',
        'xh' => 'Ipinki',
        'pt' => 'Rosa',
        'st' => 'Pinki'
    ],
    'orange' => [
        'af' => 'Oranje',
        'en' => 'Orange',
        'zu' => 'Orenji',
        'xh' => 'Iorenji',
        'pt' => 'Laranja',
        'st' => 'Lamunu'
    ],
    'yellow' => [
        'af' => 'Geel',
        'en' => 'Yellow',
        'zu' => 'Okuphuzi',
        'xh' => 'Omthubi',
        'pt' => 'Amarelo',
        'st' => 'Mosehla'
    ],
    'green' => [
        'af' => 'Groen',
        'en' => 'Green',
        'zu' => 'Oluhlaza',
        'xh' => 'Oluhlaza',
        'pt' => 'Verde',
        'st' => 'Botala'
    ],
    'blue' => [
        'af' => 'Blou',
        'en' => 'Blue',
        'zu' => 'Okuluhlaza Okwesibhakabhaka',
        'xh' => 'Ebhlowu',
        'pt' => 'Azul',
        'st' => 'Bolou'
    ],
    'purple' => [
        'af' => 'Pers',
        'en' => 'Purple',
        'zu' => 'Okumfusa',
        'xh' => 'Okumfusa',
        'pt' => 'Roxo',
        'st' => 'Moputsoa'
    ],
    'bookmark' => [
        'af' => 'Boekmerk',
        'en' => 'Bookmark',
        'zu' => 'Isimaki Sebhuku',
        'xh' => 'Isimaki Sencwadi',
        'pt' => 'Favorito',
        'st' => 'Maraka ea Buka'
    ],
    'add_note' => [
        'af' => 'Voeg nota by',
        'en' => 'Add note',
        'zu' => 'Engeza inothi',
        'xh' => 'Yongeza inqaku',
        'pt' => 'Adicionar nota',
        'st' => 'Eketsa tlhaloso'
    ],
    'ask_ai' => [
        'af' => 'Vra AI oor gedeelte',
        'en' => 'Ask AI about passage',
        'zu' => 'Buza i-AI ngendima',
        'xh' => 'Buza i-AI ngecandelo',
        'pt' => 'Perguntar à IA sobre passagem',
        'st' => 'Botsa AI ka temana'
    ],
    'cross_refs' => [
        'af' => 'Kruisverwysings',
        'en' => 'Cross Refs',
        'zu' => 'Izinkomba',
        'xh' => 'Izalathiso',
        'pt' => 'Refs Cruzadas',
        'st' => 'Litšupiso'
    ],
    'copy' => [
        'af' => 'Kopieer',
        'en' => 'Copy',
        'zu' => 'Kopisha',
        'xh' => 'Kopa',
        'pt' => 'Copiar',
        'st' => 'Kopitsa'
    ],
    'share' => [
        'af' => 'Deel',
        'en' => 'Share',
        'zu' => 'Yabelana',
        'xh' => 'Yabelana',
        'pt' => 'Compartilhar',
        'st' => 'Arolelana'
    ],
    'navigate' => [
        'af' => 'Navigeer',
        'en' => 'Navigate',
        'zu' => 'Zulazula',
        'xh' => 'Hambisa',
        'pt' => 'Navegar',
        'st' => 'Tsamaea'
    ],
    'decrease' => [
        'af' => 'Verklein',
        'en' => 'Decrease',
        'zu' => 'Nciphisa',
        'xh' => 'Nciphisa',
        'pt' => 'Diminuir',
        'st' => 'Fokotsa'
    ],
    'increase' => [
        'af' => 'Vergroot',
        'en' => 'Increase',
        'zu' => 'Khulisa',
        'xh' => 'Yandisa',
        'pt' => 'Aumentar',
        'st' => 'Eketsa'
    ],
    'remove' => [
        'af' => 'Verwyder',
        'en' => 'Remove',
        'zu' => 'Susa',
        'xh' => 'Susa',
        'pt' => 'Remover',
        'st' => 'Tlosa'
    ],

    // ==================== CALENDAR ====================
    'ai_calendar' => [
        'af' => 'AI Kalender',
        'en' => 'AI Calendar',
        'zu' => 'Ikhalenda le-AI',
        'xh' => 'Ikhalenda ye-AI',
        'pt' => 'Calendário IA',
        'st' => 'Khalendara ea AI'
    ],
    'day' => [
        'af' => 'Dag',
        'en' => 'Day',
        'zu' => 'Usuku',
        'xh' => 'Usuku',
        'pt' => 'Dia',
        'st' => 'Letsatsi'
    ],
    'week' => [
        'af' => 'Week',
        'en' => 'Week',
        'zu' => 'Iviki',
        'xh' => 'Iveki',
        'pt' => 'Semana',
        'st' => 'Beke'
    ],
    'today' => [
        'af' => 'Vandag',
        'en' => 'Today',
        'zu' => 'Namuhla',
        'xh' => 'Namhlanje',
        'pt' => 'Hoje',
        'st' => 'Kajeno'
    ],
    'sun' => [
        'af' => 'So',
        'en' => 'Sun',
        'zu' => 'Son',
        'xh' => 'ICa',
        'pt' => 'Dom',
        'st' => 'Son'
    ],
    'mon' => [
        'af' => 'Ma',
        'en' => 'Mon',
        'zu' => 'Mso',
        'xh' => 'Mvu',
        'pt' => 'Seg',
        'st' => 'Mnt'
    ],
    'tue' => [
        'af' => 'Di',
        'en' => 'Tue',
        'zu' => 'Bil',
        'xh' => 'Lwe',
        'pt' => 'Ter',
        'st' => 'Lab'
    ],
    'wed' => [
        'af' => 'Wo',
        'en' => 'Wed',
        'zu' => 'Tha',
        'xh' => 'Tha',
        'pt' => 'Qua',
        'st' => 'Lar'
    ],
    'thu' => [
        'af' => 'Do',
        'en' => 'Thu',
        'zu' => 'Sin',
        'xh' => 'Sin',
        'pt' => 'Qui',
        'st' => 'Lan'
    ],
    'fri' => [
        'af' => 'Vr',
        'en' => 'Fri',
        'zu' => 'Hla',
        'xh' => 'Hla',
        'pt' => 'Sex',
        'st' => 'Lah'
    ],
    'sat' => [
        'af' => 'Sa',
        'en' => 'Sat',
        'zu' => 'Mgq',
        'xh' => 'Mgq',
        'pt' => 'Sáb',
        'st' => 'Mok'
    ],
    // Full day names for calendar
    'sunday' => [
        'af' => 'Sondag',
        'en' => 'Sunday',
        'zu' => 'ISonto',
        'xh' => 'ICawa',
        'pt' => 'Domingo',
        'st' => 'Sontaha'
    ],
    'monday' => [
        'af' => 'Maandag',
        'en' => 'Monday',
        'zu' => 'UMsombuluko',
        'xh' => 'UMvulo',
        'pt' => 'Segunda-feira',
        'st' => 'Mantaha'
    ],
    'tuesday' => [
        'af' => 'Dinsdag',
        'en' => 'Tuesday',
        'zu' => 'ULwesibili',
        'xh' => 'ULwesibini',
        'pt' => 'Terça-feira',
        'st' => 'Labobeli'
    ],
    'wednesday' => [
        'af' => 'Woensdag',
        'en' => 'Wednesday',
        'zu' => 'ULwesithathu',
        'xh' => 'ULwesithathu',
        'pt' => 'Quarta-feira',
        'st' => 'Laboraro'
    ],
    'thursday' => [
        'af' => 'Donderdag',
        'en' => 'Thursday',
        'zu' => 'ULwesine',
        'xh' => 'ULwesine',
        'pt' => 'Quinta-feira',
        'st' => 'Labone'
    ],
    'friday' => [
        'af' => 'Vrydag',
        'en' => 'Friday',
        'zu' => 'ULwesihlanu',
        'xh' => 'ULwesihlanu',
        'pt' => 'Sexta-feira',
        'st' => 'Labohlano'
    ],
    'saturday' => [
        'af' => 'Saterdag',
        'en' => 'Saturday',
        'zu' => 'UMgqibelo',
        'xh' => 'UMgqibelo',
        'pt' => 'Sábado',
        'st' => 'Moqebelo'
    ],
    // Month names for calendar
    'january' => [
        'af' => 'Januarie',
        'en' => 'January',
        'zu' => 'UMasingana',
        'xh' => 'UJanyuwari',
        'pt' => 'Janeiro',
        'st' => 'Pherekgong'
    ],
    'february' => [
        'af' => 'Februarie',
        'en' => 'February',
        'zu' => 'UNhlolanja',
        'xh' => 'UFebruwari',
        'pt' => 'Fevereiro',
        'st' => 'Hlakola'
    ],
    'march' => [
        'af' => 'Maart',
        'en' => 'March',
        'zu' => 'UNdasa',
        'xh' => 'UMatshi',
        'pt' => 'Março',
        'st' => 'Hlakubele'
    ],
    'april' => [
        'af' => 'April',
        'en' => 'April',
        'zu' => 'UMbasa',
        'xh' => 'UEpreli',
        'pt' => 'Abril',
        'st' => 'Mmesa'
    ],
    'may' => [
        'af' => 'Mei',
        'en' => 'May',
        'zu' => 'UNhlaba',
        'xh' => 'UMeyi',
        'pt' => 'Maio',
        'st' => 'Motšeanong'
    ],
    'june' => [
        'af' => 'Junie',
        'en' => 'June',
        'zu' => 'UNhlangulana',
        'xh' => 'UJuni',
        'pt' => 'Junho',
        'st' => 'Phupjane'
    ],
    'july' => [
        'af' => 'Julie',
        'en' => 'July',
        'zu' => 'UNtulikazi',
        'xh' => 'UJulayi',
        'pt' => 'Julho',
        'st' => 'Phupu'
    ],
    'august' => [
        'af' => 'Augustus',
        'en' => 'August',
        'zu' => 'UNcwaba',
        'xh' => 'UAgasti',
        'pt' => 'Agosto',
        'st' => 'Phato'
    ],
    'september' => [
        'af' => 'September',
        'en' => 'September',
        'zu' => 'UMandulo',
        'xh' => 'USeptemba',
        'pt' => 'Setembro',
        'st' => 'Loetse'
    ],
    'october' => [
        'af' => 'Oktober',
        'en' => 'October',
        'zu' => 'UMfumfu',
        'xh' => 'UOktobha',
        'pt' => 'Outubro',
        'st' => 'Mphalane'
    ],
    'november' => [
        'af' => 'November',
        'en' => 'November',
        'zu' => 'ULwezi',
        'xh' => 'UNovemba',
        'pt' => 'Novembro',
        'st' => 'Pulungoana'
    ],
    'december' => [
        'af' => 'Desember',
        'en' => 'December',
        'zu' => 'UZibandlela',
        'xh' => 'UDisemba',
        'pt' => 'Dezembro',
        'st' => 'Tšitoe'
    ],
    // Singular appointment
    'appointment' => [
        'af' => 'Afspraak',
        'en' => 'Appointment',
        'zu' => 'Isivumelwano',
        'xh' => 'Isivumelwano',
        'pt' => 'Compromisso',
        'st' => 'Kopano'
    ],
    'create_new' => [
        'af' => 'Skep Nuwe',
        'en' => 'Create New',
        'zu' => 'Dala Okusha',
        'xh' => 'Yenza Okutsha',
        'pt' => 'Criar Novo',
        'st' => 'Theha e Ncha'
    ],
    'event' => [
        'af' => 'Aktiwiteit',
        'en' => 'Activity',
        'zu' => 'Umsebenzi',
        'xh' => 'Umsebenzi',
        'pt' => 'Atividade',
        'st' => 'Mosebetsi'
    ],
    'title' => [
        'af' => 'Titel',
        'en' => 'Title',
        'zu' => 'Isihloko',
        'xh' => 'Isihloko',
        'pt' => 'Título',
        'st' => 'Sehlooho'
    ],
    'select_room' => [
        'af' => 'Kies kamer...',
        'en' => 'Select room...',
        'zu' => 'Khetha igumbi...',
        'xh' => 'Khetha igumbi...',
        'pt' => 'Selecionar sala...',
        'st' => 'Khetha kamore...'
    ],
    'with_whom' => [
        'af' => 'Met wie?',
        'en' => 'With whom?',
        'zu' => 'Nobani?',
        'xh' => 'Nabani?',
        'pt' => 'Com quem?',
        'st' => 'Le mang?'
    ],
    'select_user' => [
        'af' => 'Kies gebruiker...',
        'en' => 'Select user...',
        'zu' => 'Khetha umsebenzisi...',
        'xh' => 'Khetha umsebenzisi...',
        'pt' => 'Selecionar usuário...',
        'st' => 'Khetha mosebelisi...'
    ],
    'type_name' => [
        'af' => 'Tik naam in...',
        'en' => 'Type name...',
        'zu' => 'Thayipha igama...',
        'xh' => 'Chwetheza igama...',
        'pt' => 'Digite nome...',
        'st' => 'Thaepa lebitso...'
    ],
    'share_with_spouse' => [
        'af' => 'Deel met gade',
        'en' => 'Share with spouse',
        'zu' => 'Yabelana nomlingani',
        'xh' => 'Yabelana nomlingani',
        'pt' => 'Compartilhar com cônjuge',
        'st' => 'Arolelana le molekane'
    ],
    'no_events' => [
        'af' => 'Geen aktiwiteite',
        'en' => 'No activities',
        'zu' => 'Akukho misebenzi',
        'xh' => 'Akukho misebenzi',
        'pt' => 'Sem atividades',
        'st' => 'Ha ho na mesebetsi'
    ],
    'confirm_delete' => [
        'af' => 'Is jy seker jy wil hierdie inskrywing verwyder?',
        'en' => 'Are you sure you want to delete this entry?',
        'zu' => 'Uqinisekile ukuthi ufuna ukususa lokhu okufakiwe?',
        'xh' => 'Uqinisekile ukuba ufuna ukucima oku okufakiweyo?',
        'pt' => 'Tem certeza de que deseja excluir esta entrada?',
        'st' => 'Na o na le bonnete ba hore o batla ho hlakola?'
    ],
    'saved_success' => [
        'af' => 'Suksesvol gestoor!',
        'en' => 'Successfully saved!',
        'zu' => 'Kugcinwe ngempumelelo!',
        'xh' => 'Igcinwe ngempumelelo!',
        'pt' => 'Salvo com sucesso!',
        'st' => 'E bolokiloe ka katleho!'
    ],
    'deleted_success' => [
        'af' => 'Suksesvol verwyder!',
        'en' => 'Successfully deleted!',
        'zu' => 'Kususiwe ngempumelelo!',
        'xh' => 'Icinyiwe ngempumelelo!',
        'pt' => 'Excluído com sucesso!',
        'st' => 'E hlakotsoe ka katleho!'
    ],
    'see_you_at' => [
        'af' => 'Sien julle',
        'en' => 'See you',
        'zu' => 'Sizobonana',
        'xh' => 'Sobonana',
        'pt' => 'Vejo vocês',
        'st' => 'Re tla bonana'
    ],
    'type' => [
        'af' => 'Tipe',
        'en' => 'Type',
        'zu' => 'Uhlobo',
        'xh' => 'Uhlobo',
        'pt' => 'Tipo',
        'st' => 'Mofuta'
    ],

    // ==================== DIARY ====================
    'ai_diary' => [
        'af' => 'AI Dagboek',
        'en' => 'AI Diary',
        'zu' => 'Idayari ye-AI',
        'xh' => 'Idayari ye-AI',
        'pt' => 'Diário IA',
        'st' => 'Buka ea Letsatsi ea AI'
    ],
    'my_diary' => [
        'af' => 'My Dagboek',
        'en' => 'My Diary',
        'zu' => 'Idayari Yami',
        'xh' => 'Idayari Yam',
        'pt' => 'Meu Diário',
        'st' => 'Buka ea Ka ea Letsatsi'
    ],
    'preserve_thoughts' => [
        'af' => 'Bewaar jou gedagtes, drome en gebede',
        'en' => 'Preserve your thoughts, dreams and prayers',
        'zu' => 'Gcina imicabango yakho, amaphupho nemithandazo',
        'xh' => 'Gcina iingcinga zakho, amaphupha nemithandazo',
        'pt' => 'Preserve seus pensamentos, sonhos e orações',
        'st' => 'Boloka mehopolo ea hao, litoro le lithapelo'
    ],
    'total_entries' => [
        'af' => 'Totale Inskrywings',
        'en' => 'Total Entries',
        'zu' => 'Okufakiwe Kuphelele',
        'xh' => 'Okufakiweyo Kupheleleyo',
        'pt' => 'Total de Entradas',
        'st' => 'Lintlha Kaofela'
    ],
    'this_month' => [
        'af' => 'Hierdie Maand',
        'en' => 'This Month',
        'zu' => 'Lenyanga',
        'xh' => 'Lenyanga',
        'pt' => 'Este Mês',
        'st' => 'Khoeli Ena'
    ],
    'day_streak' => [
        'af' => 'Dag Reeks',
        'en' => 'Day Streak',
        'zu' => 'Ukulandelana Kwezinsuku',
        'xh' => 'Ukulandelana Kweentsuku',
        'pt' => 'Sequência de Dias',
        'st' => 'Tatellano ea Matsatsi'
    ],
    'total_words' => [
        'af' => 'Totale Woorde',
        'en' => 'Total Words',
        'zu' => 'Amagama Aphelele',
        'xh' => 'Amagama Apheleleyo',
        'pt' => 'Total de Palavras',
        'st' => 'Mantsoe Kaofela'
    ],
    'timeline' => [
        'af' => 'Tydlyn',
        'en' => 'Timeline',
        'zu' => 'Uhlelo Lwesikhathi',
        'xh' => 'Uluhlu Lwexesha',
        'pt' => 'Linha do Tempo',
        'st' => 'Mothinya oa Nako'
    ],
    'gallery' => [
        'af' => 'Galery',
        'en' => 'Gallery',
        'zu' => 'Igalari',
        'xh' => 'Igalari',
        'pt' => 'Galeria',
        'st' => 'Khalari'
    ],
    'new_entry' => [
        'af' => 'Nuwe Inskrywing',
        'en' => 'New Entry',
        'zu' => 'Okufakiwe Okusha',
        'xh' => 'Okufakiweyo Okutsha',
        'pt' => 'Nova Entrada',
        'st' => 'Ntlha e Ncha'
    ],
    'search_entries' => [
        'af' => 'Soek inskrywings...',
        'en' => 'Search entries...',
        'zu' => 'Sesha okufakiwe...',
        'xh' => 'Khangela okufakiweyo...',
        'pt' => 'Pesquisar entradas...',
        'st' => 'Batla lintlha...'
    ],
    'newest_first' => [
        'af' => 'Nuutste Eerste',
        'en' => 'Newest First',
        'zu' => 'Okusha Kuqala',
        'xh' => 'Okutsha Kuqala',
        'pt' => 'Mais Recente Primeiro',
        'st' => 'Tse Ncha Pele'
    ],
    'oldest_first' => [
        'af' => 'Oudste Eerste',
        'en' => 'Oldest First',
        'zu' => 'Okudala Kuqala',
        'xh' => 'Okudala Kuqala',
        'pt' => 'Mais Antigo Primeiro',
        'st' => 'Tsa Khale Pele'
    ],
    'title_az' => [
        'af' => 'Titel A-Z',
        'en' => 'Title A-Z',
        'zu' => 'Isihloko A-Z',
        'xh' => 'Isihloko A-Z',
        'pt' => 'Título A-Z',
        'st' => 'Sehlooho A-Z'
    ],
    'all' => [
        'af' => 'Alle',
        'en' => 'All',
        'zu' => 'Konke',
        'xh' => 'Konke',
        'pt' => 'Todos',
        'st' => 'Tsohle'
    ],
    'this_week' => [
        'af' => 'Hierdie Week',
        'en' => 'This Week',
        'zu' => 'Leliviki',
        'xh' => 'Leveki',
        'pt' => 'Esta Semana',
        'st' => 'Beke Ena'
    ],
    'this_year' => [
        'af' => 'Hierdie Jaar',
        'en' => 'This Year',
        'zu' => 'Lonyaka',
        'xh' => 'Lonyaka',
        'pt' => 'Este Ano',
        'st' => 'Selemo Sena'
    ],
    'loading_entries' => [
        'af' => 'Laai inskrywings...',
        'en' => 'Loading entries...',
        'zu' => 'Ilayisha okufakiwe...',
        'xh' => 'Ilayisha okufakiweyo...',
        'pt' => 'Carregando entradas...',
        'st' => 'E kenya lintlha...'
    ],
    'loading_gallery' => [
        'af' => 'Laai galery...',
        'en' => 'Loading gallery...',
        'zu' => 'Ilayisha igalari...',
        'xh' => 'Ilayisha igalari...',
        'pt' => 'Carregando galeria...',
        'st' => 'E kenya khalari...'
    ],
    'search_through_all' => [
        'af' => 'Soek deur al jou inskrywings...',
        'en' => 'Search through all your entries...',
        'zu' => 'Sesha kuyo yonke okufakiwe kwakho...',
        'xh' => 'Khangela kuyo yonke okufakiweyo kwakho...',
        'pt' => 'Pesquisar em todas as suas entradas...',
        'st' => 'Batla ho lintlha tsohle tsa hao...'
    ],
    'titles' => [
        'af' => 'Titels',
        'en' => 'Titles',
        'zu' => 'Izihloko',
        'xh' => 'Izihloko',
        'pt' => 'Títulos',
        'st' => 'Lihlooho'
    ],
    'content' => [
        'af' => 'Inhoud',
        'en' => 'Content',
        'zu' => 'Okuqukethwe',
        'xh' => 'Okuqukethweyo',
        'pt' => 'Conteúdo',
        'st' => 'Litaba'
    ],
    'tags' => [
        'af' => 'Etikette',
        'en' => 'Tags',
        'zu' => 'Amalebula',
        'xh' => 'Iilebhuli',
        'pt' => 'Etiquetas',
        'st' => 'Limaraka'
    ],
    'start_typing_search' => [
        'af' => 'Begin tik om te soek...',
        'en' => 'Start typing to search...',
        'zu' => 'Qala ukuthayipha ukusesha...',
        'xh' => 'Qala ukuchwetheza ukukhangela...',
        'pt' => 'Comece a digitar para pesquisar...',
        'st' => 'Qala ho thaepa ho batla...'
    ],
    'date_time' => [
        'af' => 'Datum & Tyd',
        'en' => 'Date & Time',
        'zu' => 'Usuku & Isikhathi',
        'xh' => 'Umhla & Ixesha',
        'pt' => 'Data e Hora',
        'st' => 'Letsatsi le Nako'
    ],
    'my_thoughts_today' => [
        'af' => 'My gedagtes vir vandag...',
        'en' => 'My thoughts for today...',
        'zu' => 'Imicabango yami yanamuhla...',
        'xh' => 'Iingcinga zam zanamhlanje...',
        'pt' => 'Meus pensamentos para hoje...',
        'st' => 'Mehopolo ea ka ea kajeno...'
    ],
    'write_thoughts_here' => [
        'af' => 'Skryf jou gedagtes hier...',
        'en' => 'Write your thoughts here...',
        'zu' => 'Bhala imicabango yakho lapha...',
        'xh' => 'Bhala iingcinga zakho apha...',
        'pt' => 'Escreva seus pensamentos aqui...',
        'st' => 'Ngola mehopolo ea hao mona...'
    ],
    'type_tag_enter' => [
        'af' => 'Tik etiket en druk Enter...',
        'en' => 'Type tag and press Enter...',
        'zu' => 'Thayipha ilebula bese ucindezela u-Enter...',
        'xh' => 'Chwetheza ilebhuli uze ucinezele u-Enter...',
        'pt' => 'Digite etiqueta e pressione Enter...',
        'st' => 'Thaepa maraka \'me o tobetse Enter...'
    ],
    'mood' => [
        'af' => 'Gemoed',
        'en' => 'Mood',
        'zu' => 'Umuzwa',
        'xh' => 'Imvakalelo',
        'pt' => 'Humor',
        'st' => 'Maikutlo'
    ],
    'weather' => [
        'af' => 'Weer',
        'en' => 'Weather',
        'zu' => 'Isimo Sezulu',
        'xh' => 'Imozulu',
        'pt' => 'Clima',
        'st' => 'Boemo ba Leholimo'
    ],
    'reminder' => [
        'af' => 'Herinnering',
        'en' => 'Reminder',
        'zu' => 'Isikhumbuzo',
        'xh' => 'Isikhumbuzo',
        'pt' => 'Lembrete',
        'st' => 'Khopotso'
    ],
    'no_reminder' => [
        'af' => 'Geen herinnering',
        'en' => 'No reminder',
        'zu' => 'Akukho sikhumbuzo',
        'xh' => 'Akukho sikhumbuzo',
        'pt' => 'Sem lembrete',
        'st' => 'Ha ho na khopotso'
    ],
    'minutes_before' => [
        'af' => 'minute voor',
        'en' => 'minutes before',
        'zu' => 'imizuzu ngaphambi',
        'xh' => 'imizuzu phambi',
        'pt' => 'minutos antes',
        'st' => 'metsotso pele'
    ],
    'hour_before' => [
        'af' => 'uur voor',
        'en' => 'hour before',
        'zu' => 'ihora ngaphambi',
        'xh' => 'iyure phambi',
        'pt' => 'hora antes',
        'st' => 'hora pele'
    ],
    'hours_before' => [
        'af' => 'ure voor',
        'en' => 'hours before',
        'zu' => 'amahora ngaphambi',
        'xh' => 'iiyure phambi',
        'pt' => 'horas antes',
        'st' => 'lihora pele'
    ],
    'day_before' => [
        'af' => 'dag voor',
        'en' => 'day before',
        'zu' => 'usuku ngaphambi',
        'xh' => 'usuku phambi',
        'pt' => 'dia antes',
        'st' => 'letsatsi pele'
    ],
    'add_to_calendar' => [
        'af' => 'Voeg by kalender',
        'en' => 'Add to calendar',
        'zu' => 'Engeza kukhalenda',
        'xh' => 'Yongeza kwikhalenda',
        'pt' => 'Adicionar ao calendário',
        'st' => 'Eketsa khalendareng'
    ],
    'ai_assist' => [
        'af' => 'AI Hulp',
        'en' => 'AI Assist',
        'zu' => 'Usizo lwe-AI',
        'xh' => 'Uncedo lwe-AI',
        'pt' => 'Assistência IA',
        'st' => 'Thuso ea AI'
    ],
    'share_entry' => [
        'af' => 'Deel Inskrywing',
        'en' => 'Share Entry',
        'zu' => 'Yabelana Ngokufakiwe',
        'xh' => 'Yabelana Ngokufakiweyo',
        'pt' => 'Compartilhar Entrada',
        'st' => 'Arolelana ka Ntlha'
    ],
    'share_with_friend' => [
        'af' => 'Deel met Vriend',
        'en' => 'Share with Friend',
        'zu' => 'Yabelana Nomngani',
        'xh' => 'Yabelana Nomhlobo',
        'pt' => 'Compartilhar com Amigo',
        'st' => 'Arolelana le Motsoalle'
    ],
    'copy_link' => [
        'af' => 'Kopieer Skakel',
        'en' => 'Copy Link',
        'zu' => 'Kopisha Isixhumanisi',
        'xh' => 'Kopa Ikhonkco',
        'pt' => 'Copiar Link',
        'st' => 'Kopitsa Khokahano'
    ],
    'export_pdf' => [
        'af' => 'Voer Uit as PDF',
        'en' => 'Export as PDF',
        'zu' => 'Thumela nge-PDF',
        'xh' => 'Thumela nge-PDF',
        'pt' => 'Exportar como PDF',
        'st' => 'Romela joalo ka PDF'
    ],
    'words' => [
        'af' => 'woorde',
        'en' => 'words',
        'zu' => 'amagama',
        'xh' => 'amagama',
        'pt' => 'palavras',
        'st' => 'mantsoe'
    ],

    // ==================== PRAYERS ====================
    'prayers_testimonies' => [
        'af' => 'Gebede',
        'en' => 'Prayers',
        'zu' => 'Imithandazo',
        'xh' => 'Imithandazo',
        'pt' => 'Orações',
        'st' => 'Lithapelo'
    ],
    'share_heart_pray' => [
        'af' => 'Deel jou hart, bid saam, vier saam',
        'en' => 'Share your heart, pray together, celebrate together',
        'zu' => 'Yabelana ngenhliziyo yakho, thandaza ndawonye, gubha ndawonye',
        'xh' => 'Yabelana ngentliziyo yakho, thandaza kunye, bhiyozela kunye',
        'pt' => 'Compartilhe seu coração, ore junto, celebre junto',
        'st' => 'Arolelana ka pelo ea hao, rapela hammoho, keteka hammoho'
    ],
    'share_prayer_testimony' => [
        'af' => 'Deel jou gebedsnood',
        'en' => 'Share your prayer need',
        'zu' => 'Yabelana ngesidingo sakho somthandazo',
        'xh' => 'Yabelana ngesidingo sakho somthandazo',
        'pt' => 'Compartilhe sua necessidade de oração',
        'st' => 'Arolelana ka tlhoko ea hao ea thapelo'
    ],
    'prayer' => [
        'af' => 'Gebed',
        'en' => 'Prayer',
        'zu' => 'Umthandazo',
        'xh' => 'Umthandazo',
        'pt' => 'Oração',
        'st' => 'Thapelo'
    ],
    'testimony' => [
        'af' => 'Getuienis',
        'en' => 'Testimony',
        'zu' => 'Ubufakazi',
        'xh' => 'Ubungqina',
        'pt' => 'Testemunho',
        'st' => 'Bopaki'
    ],
    'share_heart_here' => [
        'af' => 'Deel jou hart hier...',
        'en' => 'Share your heart here...',
        'zu' => 'Yabelana ngenhliziyo yakho lapha...',
        'xh' => 'Yabelana ngentliziyo yakho apha...',
        'pt' => 'Compartilhe seu coração aqui...',
        'st' => 'Arolelana ka pelo ea hao mona...'
    ],
    'choose_photo' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (uyazikhethela)',
        'xh' => 'Khetha umfanekiso (uyazikhethela)',
        'pt' => 'Escolher foto (opcional)',
        'st' => 'Khetha senepe (ka boithaopo)'
    ],
    'loading_prayers' => [
        'af' => 'Laai gebede...',
        'en' => 'Loading prayers...',
        'zu' => 'Ilayisha imithandazo...',
        'xh' => 'Ilayisha imithandazo...',
        'pt' => 'Carregando orações...',
        'st' => 'E kenya lithapelo...'
    ],
    'comments' => [
        'af' => 'Kommentaar',
        'en' => 'Comments',
        'zu' => 'Amazwana',
        'xh' => 'Amagqabaza',
        'pt' => 'Comentários',
        'st' => 'Maikutlo'
    ],
    'write_encouraging' => [
        'af' => 'Skryf \'n bemoedigende woord...',
        'en' => 'Write an encouraging word...',
        'zu' => 'Bhala igama elikhuthazayo...',
        'xh' => 'Bhala ilizwi elikhuthazayo...',
        'pt' => 'Escreva uma palavra de encorajamento...',
        'st' => 'Ngola lentsoe le khothaletso...'
    ],
    'send' => [
        'af' => 'Stuur',
        'en' => 'Send',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Enviar',
        'st' => 'Romela'
    ],

    // ==================== PRAYERS EXTRA ====================
    'prayers_subtitle' => [
        'af' => 'Deel jou hart, bid saam',
        'en' => 'Share your heart, pray together',
        'zu' => 'Yabelana ngenhliziyo yakho, thandaza ndawonye',
        'xh' => 'Yabelana ngentliziyo yakho, thandaza kunye',
        'pt' => 'Compartilhe seu coração, ore junto',
        'st' => 'Arolelana ka pelo ea hao, rapela hammoho'
    ],
    'share_heart_placeholder' => [
        'af' => 'Deel jou hart hier...',
        'en' => 'Share your heart here...',
        'zu' => 'Yabelana ngenhliziyo yakho lapha...',
        'xh' => 'Yabelana ngentliziyo yakho apha...',
        'pt' => 'Compartilhe seu coração aqui...',
        'st' => 'Arolelana ka pelo ea hao mona...'
    ],
    'choose_photo_optional' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (uyazikhethela)',
        'xh' => 'Khetha umfanekiso (uyazikhethela)',
        'pt' => 'Escolher foto (opcional)',
        'st' => 'Khetha senepe (ka boithaopo)'
    ],
    'write_encouraging_word' => [
        'af' => 'Skryf \'n bemoedigende woord...',
        'en' => 'Write an encouraging word...',
        'zu' => 'Bhala igama elikhuthazayo...',
        'xh' => 'Bhala ilizwi elikhuthazayo...',
        'pt' => 'Escreva uma palavra de encorajamento...',
        'st' => 'Ngola lentsoe le khothaletso...'
    ],

    // ==================== GOSPEL MEDIA ====================
    'no_rooms_available' => [
        'af' => 'Geen kamers beskikbaar nie. Kontak admin.',
        'en' => 'No rooms available. Contact admin.',
        'zu' => 'Akukho magumbi atholakalayo. Xhumana no-admin.',
        'xh' => 'Akukho magumbi afumanekayo. Qhagamshelana no-admin.',
        'pt' => 'Nenhuma sala disponível. Contate o administrador.',
        'st' => 'Ha ho na likamore tse fumanehang. Ikopanye le motsamaisi.'
    ],
    'no_room_access' => [
        'af' => 'Jy het nie toegang tot hierdie kamer nie.',
        'en' => 'You do not have access to this room.',
        'zu' => 'Awunayo imvume yokungena kuleli gumbi.',
        'xh' => 'Awunayo imvume yokungena kweli gumbi.',
        'pt' => 'Você não tem acesso a esta sala.',
        'st' => 'Ha o na tokelo ea ho kena kamoreng ena.'
    ],
    'rooms' => [
        'af' => 'Kamers',
        'en' => 'Rooms',
        'zu' => 'Amagumbi',
        'xh' => 'Amagumbi',
        'pt' => 'Salas',
        'st' => 'Likamore'
    ],
    'new_post' => [
        'af' => 'Nuwe Plasing',
        'en' => 'New Post',
        'zu' => 'Ukuposa Okusha',
        'xh' => 'Ukuposa Okutsha',
        'pt' => 'Nova Postagem',
        'st' => 'Poso e Ncha'
    ],
    'post' => [
        'af' => 'Plaas',
        'en' => 'Post',
        'zu' => 'Posa',
        'xh' => 'Posa',
        'pt' => 'Postar',
        'st' => 'Posa'
    ],
    'what_share' => [
        'af' => 'Wat wil jy deel?',
        'en' => 'What do you want to share?',
        'zu' => 'Ufuna ukwabelana ngani?',
        'xh' => 'Ufuna ukwabelana ngantoni?',
        'pt' => 'O que você quer compartilhar?',
        'st' => 'O batla ho arolelana ka eng?'
    ],
    'date_time_short' => [
        'af' => 'Datum/Tyd',
        'en' => 'Date/Time',
        'zu' => 'Usuku/Isikhathi',
        'xh' => 'Umhla/Ixesha',
        'pt' => 'Data/Hora',
        'st' => 'Letsatsi/Nako'
    ],
    'place' => [
        'af' => 'Plek',
        'en' => 'Place',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local',
        'st' => 'Sebaka'
    ],
    'photo' => [
        'af' => 'Foto',
        'en' => 'Photo',
        'zu' => 'Isithombe',
        'xh' => 'Umfanekiso',
        'pt' => 'Foto',
        'st' => 'Senepe'
    ],
    'no_posting_rights' => [
        'af' => 'Jy het nie plaas-regte in hierdie kamer nie.',
        'en' => 'You do not have posting rights in this room.',
        'zu' => 'Awunayo ilungelo lokuposa kuleli gumbi.',
        'xh' => 'Awunayo ilungelo lokuposa kweli gumbi.',
        'pt' => 'Você não tem direitos de postagem nesta sala.',
        'st' => 'Ha o na tokelo ea ho posa kamoreng ena.'
    ],
    'posts' => [
        'af' => 'Plasings',
        'en' => 'Posts',
        'zu' => 'Iziposa',
        'xh' => 'Iziposa',
        'pt' => 'Postagens',
        'st' => 'Liposo'
    ],
    'loading_posts' => [
        'af' => 'Laai plasings...',
        'en' => 'Loading posts...',
        'zu' => 'Ilayisha iziposa...',
        'xh' => 'Ilayisha iziposa...',
        'pt' => 'Carregando postagens...',
        'st' => 'E kenya liposo...'
    ],
    'gm_read_only' => [
        'af' => 'Lees-alleen',
        'en' => 'Read only',
        'zu' => 'Ukufunda kuphela',
        'xh' => 'Ukufunda kuphela',
        'pt' => 'Somente leitura',
        'st' => 'Ho bala feela'
    ],
    'gm_you_are_here' => [
        'af' => 'Jy is hier',
        'en' => 'You are here',
        'zu' => 'Ulapha',
        'xh' => 'Ulapha',
        'pt' => 'Você está aqui',
        'st' => 'O mona'
    ],

    // ==================== ROOMS PAGE ====================
    'your_rooms' => [
        'af' => 'Jou Kamers',
        'en' => 'Your Rooms',
        'zu' => 'Amagumbi Akho',
        'xh' => 'Amagumbi Akho',
        'pt' => 'Suas Salas',
        'st' => 'Likamore tsa Hao'
    ],
    'town' => [
        'af' => 'Dorp',
        'en' => 'Town',
        'zu' => 'Idolobha',
        'xh' => 'Idolophu',
        'pt' => 'Cidade',
        'st' => 'Toropo'
    ],
    'age' => [
        'af' => 'Ouderdom',
        'en' => 'Age',
        'zu' => 'Iminyaka',
        'xh' => 'Iminyaka',
        'pt' => 'Idade',
        'st' => 'Lilemo'
    ],
    'years' => [
        'af' => 'jaar',
        'en' => 'years',
        'zu' => 'iminyaka',
        'xh' => 'iminyaka',
        'pt' => 'anos',
        'st' => 'lilemo'
    ],
    'amp_level' => [
        'af' => 'Amp Vlak',
        'en' => 'Amp Level',
        'zu' => 'Izinga Le-Amp',
        'xh' => 'Izinga Le-Amp',
        'pt' => 'Nível do Cargo',
        'st' => 'Boemo ba Amp'
    ],
    'apostle_desc' => [
        'af' => 'Apostel (Kan slegs opsienerskappe kies)',
        'en' => 'Apostle (Can only choose oversights)',
        'zu' => 'Umphostoli (Angakhetha ukuqashwa kuphela)',
        'xh' => 'Umpostile (Angakhetha ukongamela kuphela)',
        'pt' => 'Apóstolo (Pode apenas escolher supervisões)',
        'st' => 'Moapostola (A ka khetha litlhokomelo feela)'
    ],
    'amp_2_6_desc' => [
        'af' => 'Amp 2-6 (Kan gemeentes & jeug in eie dorp kies)',
        'en' => 'Amp 2-6 (Can choose congregations & youth in own town)',
        'zu' => 'Amp 2-6 (Angakhetha amabandla nentsha edolobheni lakhe)',
        'xh' => 'Amp 2-6 (Angakhetha amabandla nolutsha edolophini yakhe)',
        'pt' => 'Cargo 2-6 (Pode escolher congregações e jovens na própria cidade)',
        'st' => 'Amp 2-6 (A ka khetha liphuthego le batjha toropong ea hae)'
    ],
    'amp_8_desc' => [
        'af' => 'Amp 8+ (Kan slegs gemeente & opsienerskap sien, behalwe as bygevoeg)',
        'en' => 'Amp 8+ (Can only see congregation & oversight, unless added)',
        'zu' => 'Amp 8+ (Angabona ibandla nokuqashwa kuphela, ngaphandle uma wengezwe)',
        'xh' => 'Amp 8+ (Angabona ibandla nokongamela kuphela, ngaphandle kokuba wongezwe)',
        'pt' => 'Cargo 8+ (Pode ver apenas congregação e supervisão, a menos que adicionado)',
        'st' => 'Amp 8+ (A ka bona phuthego le tlhokomelo feela, ntle le ha a eketsoe)'
    ],
    'amp_7_desc' => [
        'af' => 'Amp 7 (Kan jeug/sondagskool in eie dorp kies)',
        'en' => 'Amp 7 (Can choose youth/sunday school in own town)',
        'zu' => 'Amp 7 (Angakhetha intsha/isikole sesonto edolobheni lakhe)',
        'xh' => 'Amp 7+ (Angakhetha ulutsha/isikolo sangesonto edolophini yakhe)',
        'pt' => 'Cargo 7 (Pode escolher jovens/escola dominical na própria cidade)',
        'st' => 'Amp 7 (A ka khetha batjha/sekolo sa Sontaha toropong ea hae)'
    ],
    'automatic' => [
        'af' => 'Outomaties',
        'en' => 'Automatic',
        'zu' => 'Okuzenzakalelayo',
        'xh' => 'Okuzenzakalelayo',
        'pt' => 'Automático',
        'st' => 'Ka bo eona'
    ],
    'automatic_membership' => [
        'af' => 'Outomatiese lidmaatskap',
        'en' => 'Automatic membership',
        'zu' => 'Ubulungu obuzenzakalelayo',
        'xh' => 'Ubulungu obuzenzakalelayo',
        'pt' => 'Associação automática',
        'st' => 'Botho bo iketsoang'
    ],
    'officer_can_choose' => [
        'af' => 'As offisier kan jy \'n ander gemeente kies',
        'en' => 'As officer you can choose another congregation',
        'zu' => 'Njengesikhulu ungakhetha elinye ibandla',
        'xh' => 'Njengegosa ungakhetha elinye ibandla',
        'pt' => 'Como oficial você pode escolher outra congregação',
        'st' => 'Joalo ka ofisiri o ka khetha phuthego e \'ngoe'
    ],
    'auto_under_16' => [
        'af' => 'Outomaties (onder 16 jaar)',
        'en' => 'Automatic (under 16 years)',
        'zu' => 'Okuzenzakalelayo (ngaphansi kweminyaka engu-16)',
        'xh' => 'Okuzenzakalelayo (ngaphantsi kweminyaka eli-16)',
        'pt' => 'Automático (menores de 16 anos)',
        'st' => 'Ka bo eona (ka tlase ho lilemo tse 16)'
    ],
    'auto_16_25' => [
        'af' => 'Outomaties (16-25 jaar, ongetroud)',
        'en' => 'Automatic (16-25 years, unmarried)',
        'zu' => 'Okuzenzakalelayo (iminyaka engu-16-25, ongashadile)',
        'xh' => 'Okuzenzakalelayo (iminyaka eli-16-25, ongatshatanga)',
        'pt' => 'Automático (16-25 anos, solteiro)',
        'st' => 'Ka bo eona (lilemo tse 16-25, ha o nyaloa)'
    ],
    'auto_short' => [
        'af' => 'Outo',
        'en' => 'Auto',
        'zu' => 'Okhozenzakalela',
        'xh' => 'Okhozenzakalela',
        'pt' => 'Auto',
        'st' => 'Auto'
    ],
    'leave' => [
        'af' => 'Los',
        'en' => 'Leave',
        'zu' => 'Shiya',
        'xh' => 'Shiya',
        'pt' => 'Sair',
        'st' => 'Tlohela'
    ],
    'joined' => [
        'af' => 'Gekose',
        'en' => 'Joined',
        'zu' => 'Ujoyine',
        'xh' => 'Uzibandakanye',
        'pt' => 'Associado',
        'st' => 'O kene'
    ],
    'available' => [
        'af' => 'Beskikbaar',
        'en' => 'Available',
        'zu' => 'Iyatholakala',
        'xh' => 'Iyafumaneka',
        'pt' => 'Disponível',
        'st' => 'E fumaneha'
    ],
    'join' => [
        'af' => 'Sluit aan',
        'en' => 'Join',
        'zu' => 'Joyina',
        'xh' => 'Joyina',
        'pt' => 'Entrar',
        'st' => 'Kena'
    ],
    'no_rooms' => [
        'af' => 'Geen kamers beskikbaar nie.',
        'en' => 'No rooms available.',
        'zu' => 'Akukho magumbi atholakalayo.',
        'xh' => 'Akukho magumbi afumanekayo.',
        'pt' => 'Nenhuma sala disponível.',
        'st' => 'Ha ho na likamore tse fumanehang.'
    ],
    'back_to_media' => [
        'af' => 'Terug na Media',
        'en' => 'Back to Media',
        'zu' => 'Buyela ku-Media',
        'xh' => 'Buyela ku-Media',
        'pt' => 'Voltar para Mídia',
        'st' => 'Khutlela ho Media'
    ],
    'confirm_leave_congregation' => [
        'af' => 'Is jy seker jy wil hierdie gemeente los? Jy sal \'n ander moet kies.',
        'en' => 'Are you sure you want to leave this congregation? You will need to choose another.',
        'zu' => 'Uqinisekile ukuthi ufuna ukushiya leli bandla? Uzodinga ukukhetha elinye.',
        'xh' => 'Uqinisekile ukuba ufuna ukushiya eli bandla? Kuya kufuneka ukhethe elinye.',
        'pt' => 'Tem certeza de que deseja sair desta congregação? Você precisará escolher outra.',
        'st' => 'Na o na le bonnete ba hore o batla ho tlohela phuthego ena? O tla tlameha ho khetha e \'ngoe.'
    ],
    'confirm_leave_room' => [
        'af' => 'Is jy seker jy wil los',
        'en' => 'Are you sure you want to leave',
        'zu' => 'Uqinisekile ukuthi ufuna ukushiya',
        'xh' => 'Uqinisekile ukuba ufuna ukushiya',
        'pt' => 'Tem certeza de que deseja sair',
        'st' => 'Na o na le bonnete ba hore o batla ho tlohela'
    ],
    'successfully_joined' => [
        'af' => 'Suksesvol aangesluit!',
        'en' => 'Successfully joined!',
        'zu' => 'Ujoyine ngempumelelo!',
        'xh' => 'Uzibandakanye ngempumelelo!',
        'pt' => 'Associado com sucesso!',
        'st' => 'O kene ka katleho!'
    ],
    'successfully_left' => [
        'af' => 'Suksesvol gelos!',
        'en' => 'Successfully left!',
        'zu' => 'Ushiye ngempumelelo!',
        'xh' => 'Ushiye ngempumelelo!',
        'pt' => 'Saiu com sucesso!',
        'st' => 'O tlohetse ka katleho!'
    ],
    'cannot_leave_oversight' => [
        'af' => 'Jy kan nie jou opsienerskap los nie',
        'en' => 'You cannot leave your oversight',
        'zu' => 'Awukwazi ukushiya ukuqashwa kwakho',
        'xh' => 'Awukwazi ukushiya ukongamela kwakho',
        'pt' => 'Você não pode sair da sua supervisão',
        'st' => 'Ha o khone ho tlohela tlhokomelo ea hao'
    ],
    'cannot_leave_congregation' => [
        'af' => 'Jy kan nie jou gemeente los nie',
        'en' => 'You cannot leave your congregation',
        'zu' => 'Awukwazi ukushiya ibandla lakho',
        'xh' => 'Awukwazi ukushiya ibandla lakho',
        'pt' => 'Você não pode sair da sua congregação',
        'st' => 'Ha o khone ho tlohela phuthego ea hao'
    ],

    // ==================== PROFILE PAGE ====================
    'export' => [
        'af' => 'Voer Uit',
        'en' => 'Export',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Exportar',
        'st' => 'Romela'
    ],
    'personal_information' => [
        'af' => 'Persoonlike Inligting',
        'en' => 'Personal Information',
        'zu' => 'Ulwazi Lomuntu Siqu',
        'xh' => 'Ulwazi Lobuqu',
        'pt' => 'Informações Pessoais',
        'st' => 'Lintlha tsa Botho'
    ],
    'church_information' => [
        'af' => 'Kerk Inligting',
        'en' => 'Church Information',
        'zu' => 'Ulwazi Lwebandla',
        'xh' => 'Ulwazi Lwecawa',
        'pt' => 'Informações da Igreja',
        'st' => 'Lintlha tsa Kereke'
    ],
    'office' => [
        'af' => 'Amp',
        'en' => 'Officer',
        'zu' => 'Isikhundla',
        'xh' => 'Isikhundla',
        'pt' => 'Cargo',
        'st' => 'Kantoro'
    ],
    'gender' => [
        'af' => 'Geslag',
        'en' => 'Gender',
        'zu' => 'Ubulili',
        'xh' => 'Isini',
        'pt' => 'Gênero',
        'st' => 'Bong'
    ],
    'view_profile' => [
        'af' => 'Kyk profiel',
        'en' => 'View profile',
        'zu' => 'Buka iphrofayela',
        'xh' => 'Jonga iprofayile',
        'pt' => 'Ver perfil',
        'st' => 'Sheba profaele'
    ],
    'edit_user' => [
        'af' => 'Wysig Gebruiker',
        'en' => 'Edit User',
        'zu' => 'Hlela Umsebenzisi',
        'xh' => 'Hlela Umsebenzisi',
        'pt' => 'Editar Usuário',
        'st' => 'Fetola Mosebelisi'
    ],

    // ==================== CALENDAR VIEW PAGE ====================
    'pdf' => [
        'af' => 'PDF',
        'en' => 'PDF',
        'zu' => 'PDF',
        'xh' => 'PDF',
        'pt' => 'PDF',
        'st' => 'PDF'
    ],
    'excel' => [
        'af' => 'Excel',
        'en' => 'Excel',
        'zu' => 'Excel',
        'xh' => 'Excel',
        'pt' => 'Excel',
        'st' => 'Excel'
    ],
    'generating_pdf' => [
        'af' => 'Genereer PDF...',
        'en' => 'Generating PDF...',
        'zu' => 'Kukhiqizwa i-PDF...',
        'xh' => 'Kuveliswa i-PDF...',
        'pt' => 'Gerando PDF...',
        'st' => 'E etsa PDF...'
    ],
    'generating_excel' => [
        'af' => 'Genereer Excel...',
        'en' => 'Generating Excel...',
        'zu' => 'Kukhiqizwa i-Excel...',
        'xh' => 'Kuveliswa i-Excel...',
        'pt' => 'Gerando Excel...',
        'st' => 'E etsa Excel...'
    ],
    'pdf_exported' => [
        'af' => 'PDF uitgevoer!',
        'en' => 'PDF exported!',
        'zu' => 'I-PDF ithunyelwe!',
        'xh' => 'I-PDF ithunyelwe!',
        'pt' => 'PDF exportado!',
        'st' => 'PDF e rometsoe!'
    ],
    'excel_exported' => [
        'af' => 'Excel uitgevoer!',
        'en' => 'Excel exported!',
        'zu' => 'I-Excel ithunyelwe!',
        'xh' => 'I-Excel ithunyelwe!',
        'pt' => 'Excel exportado!',
        'st' => 'Excel e rometsoe!'
    ],
    'export_failed' => [
        'af' => 'Uitvoer het misluk',
        'en' => 'Export failed',
        'zu' => 'Ukuthumela kwehlulekile',
        'xh' => 'Ukuthumela akuphumelelanga',
        'pt' => 'Exportação falhou',
        'st' => 'Ho romela ho hlolehile'
    ],

    // ==================== NOTIFICATIONS PAGE ====================
    'ai_notifications' => [
        'af' => 'AI Kennisgewings',
        'en' => 'AI Notifications',
        'zu' => 'Izaziso ze-AI',
        'xh' => 'Izaziso ze-AI',
        'pt' => 'Notificações IA',
        'st' => 'Litsebiso tsa AI'
    ],
    'stay_updated' => [
        'af' => 'Bly Op Hoogte van Jou Reis',
        'en' => 'Stay Updated on Your Journey',
        'zu' => 'Hlala Ufundiswe Ngohambo Lwakho',
        'xh' => 'Hlala Uhlaziwe Ngohambo Lwakho',
        'pt' => 'Mantenha-se Atualizado na Sua Jornada',
        'st' => 'Lula o Tsebisoa ka Leeto la Hao'
    ],
    'mark_all_read' => [
        'af' => 'Merk Alles as Gelees',
        'en' => 'Mark All as Read',
        'zu' => 'Phawula Konke Njengokufundiwe',
        'xh' => 'Phawula Konke Njengokufundiweyo',
        'pt' => 'Marcar Tudo como Lido',
        'st' => 'Tšoaea Tsohle e le Baliloe'
    ],
    'filter' => [
        'af' => 'Filter',
        'en' => 'Filter',
        'zu' => 'Hlunga',
        'xh' => 'Hlela',
        'pt' => 'Filtrar',
        'st' => 'Hlophisa'
    ],
    'unread' => [
        'af' => 'Ongelees',
        'en' => 'Unread',
        'zu' => 'Okungafundiwe',
        'xh' => 'Okungafundiwe',
        'pt' => 'Não Lido',
        'st' => 'Tse sa Baloa'
    ],
    'reminders' => [
        'af' => 'Herinnerings',
        'en' => 'Reminders',
        'zu' => 'Izikhumbuzi',
        'xh' => 'Izikhumbuzo',
        'pt' => 'Lembretes',
        'st' => 'Likhopotso'
    ],
    'info' => [
        'af' => 'Inligting',
        'en' => 'Info',
        'zu' => 'Ulwazi',
        'xh' => 'Ulwazi',
        'pt' => 'Info',
        'st' => 'Lintlha'
    ],
    'your_notifications' => [
        'af' => 'Jou Kennisgewings',
        'en' => 'Your Notifications',
        'zu' => 'Izaziso Zakho',
        'xh' => 'Izaziso Zakho',
        'pt' => 'Suas Notificações',
        'st' => 'Litsebiso tsa Hao'
    ],
    'no_notifications' => [
        'af' => 'Geen kennisgewings nie',
        'en' => 'No notifications',
        'zu' => 'Akukho zaziso',
        'xh' => 'Akukho zaziso',
        'pt' => 'Sem notificações',
        'st' => 'Ha ho na litsebiso'
    ],
    'mark_as_read' => [
        'af' => 'Merk as gelees',
        'en' => 'Mark as read',
        'zu' => 'Phawula njengokufundiwe',
        'xh' => 'Phawula njengokufundiweyo',
        'pt' => 'Marcar como lido',
        'st' => 'Tšoaea e le e baliloe'
    ],
    'are_you_sure' => [
        'af' => 'Is jy seker?',
        'en' => 'Are you sure?',
        'zu' => 'Uqinisekile?',
        'xh' => 'Uqinisekile?',
        'pt' => 'Tem certeza?',
        'st' => 'Na o na le bonnete?'
    ],
    'just_now' => [
        'af' => 'Nou net',
        'en' => 'Just now',
        'zu' => 'Manje nje',
        'xh' => 'Ngoku nje',
        'pt' => 'Agora mesmo',
        'st' => 'Hona joale'
    ],
    'minutes_ago' => [
        'af' => 'minute gelede',
        'en' => 'minutes ago',
        'zu' => 'amaminithi edlule',
        'xh' => 'imizuzu edlulileyo',
        'pt' => 'minutos atrás',
        'st' => 'metsotso e fetileng'
    ],
    'hours_ago' => [
        'af' => 'ure gelede',
        'en' => 'hours ago',
        'zu' => 'amahora edlule',
        'xh' => 'iiyure ezidlulileyo',
        'pt' => 'horas atrás',
        'st' => 'lihora tse fetileng'
    ],
    'days_ago' => [
        'af' => 'dae gelede',
        'en' => 'days ago',
        'zu' => 'izinsuku ezedlule',
        'xh' => 'iintsuku ezidlulileyo',
        'pt' => 'dias atrás',
        'st' => 'matsatsi a fetileng'
    ],

    // ==================== SING EMMANUEL PAGE ====================
    'sing_emmanuel_title' => [
        'af' => 'Sing Emmanuel - Getuienis van Geloof',
        'en' => 'Sing Emmanuel - Testimony of Faith',
        'zu' => 'Cula Emmanuel - Ubufakazi Bokholo',
        'xh' => 'Cula Emmanuel - Ubungqina Bokholo',
        'pt' => 'Cantar Emmanuel - Testemunho de Fé',
        'st' => 'Bina Emmanuel - Bopaki ba Tumelo'
    ],
    'praise_lord_song' => [
        'af' => 'Loof die Here met sang en jubel',
        'en' => 'Praise the Lord with song and jubilation',
        'zu' => 'Dumisani iNkosi ngengoma nokujabula',
        'xh' => 'Dumisani iNkosi ngengoma nokuvuya',
        'pt' => 'Louvai ao Senhor com cânticos e júbilo',
        'st' => 'Bokang Morena ka lipina le thabo'
    ],
    'choose_song' => [
        'af' => 'Kies \'n Lied',
        'en' => 'Choose a Song',
        'zu' => 'Khetha Ingoma',
        'xh' => 'Khetha Ingoma',
        'pt' => 'Escolha uma Canção',
        'st' => 'Khetha Sefela'
    ],
    'search_song' => [
        'af' => 'Soek Lied',
        'en' => 'Search Song',
        'zu' => 'Sesha Ingoma',
        'xh' => 'Khangela Ingoma',
        'pt' => 'Pesquisar Canção',
        'st' => 'Batla Sefela'
    ],
    'play_song' => [
        'af' => 'Speel lied',
        'en' => 'Play song',
        'zu' => 'Dlala ingoma',
        'xh' => 'Dlala ingoma',
        'pt' => 'Tocar canção',
        'st' => 'Bapala sefela'
    ],
    'player_play' => [
        'af' => 'Speel',
        'en' => 'Play',
        'zu' => 'Dlala',
        'xh' => 'Dlala',
        'pt' => 'Tocar',
        'st' => 'Bapala'
    ],
    'player_pause' => [
        'af' => 'Pouse',
        'en' => 'Pause',
        'zu' => 'Misa okwesikhashana',
        'xh' => 'Nqumama',
        'pt' => 'Pausar',
        'st' => 'Emisa hanyane'
    ],
    'player_loading' => [
        'af' => 'Laai...',
        'en' => 'Loading...',
        'zu' => 'Iyalayisha...',
        'xh' => 'Iyalayisha...',
        'pt' => 'A carregar...',
        'st' => 'E a kenya...'
    ],
    'player_error' => [
        'af' => 'Kon nie die lied laai nie. Probeer weer.',
        'en' => 'Could not load the song. Please try again.',
        'zu' => 'Ayikwazanga ukulayisha ingoma. Zama futhi.',
        'xh' => 'Ayikwazanga ukulayisha ingoma. Zama kwakhona.',
        'pt' => 'Não foi possível carregar a canção. Tente novamente.',
        'st' => 'Ha e a khona ho kenya sefela. Leka hape.'
    ],
    'player_verses_prompt' => [
        'af' => 'Hoeveel verse?',
        'en' => 'How many verses?',
        'zu' => 'Mangaki amavesi?',
        'xh' => 'Zingaphi iivesi?',
        'pt' => 'Quantas estrofes?',
        'st' => 'Litemana tse kae?'
    ],
    'player_1_verse' => [
        'af' => '1 vers',
        'en' => '1 verse',
        'zu' => 'Ivesi eli-1',
        'xh' => 'Ivesi e-1',
        'pt' => '1 estrofe',
        'st' => 'Temana e 1'
    ],
    'player_2_verses' => [
        'af' => '2 verse',
        'en' => '2 verses',
        'zu' => 'Amavesi ama-2',
        'xh' => 'Iivesi ezi-2',
        'pt' => '2 estrofes',
        'st' => 'Litemana tse 2'
    ],
    'enter_song_number' => [
        'af' => 'Tik liednommer in',
        'en' => 'Enter song number',
        'zu' => 'Faka inombolo yengoma',
        'xh' => 'Faka inombolo yengoma',
        'pt' => 'Digite o número da canção',
        'st' => 'Kenya nomoro ea sefela'
    ],
    'hymn' => [
        'af' => 'Lied',
        'en' => 'Hymn',
        'zu' => 'Ihubo',
        'xh' => 'Iculo',
        'pt' => 'Hino',
        'st' => 'Sefela'
    ],
    'song_not_found' => [
        'af' => 'Lied nie gevind nie.',
        'en' => 'Song not found.',
        'zu' => 'Ingoma ayitholakali.',
        'xh' => 'Ingoma ayifumaneki.',
        'pt' => 'Canção não encontrada.',
        'st' => 'Sefela ha se fumanoe.'
    ],
    'song_not_in_afrikaans' => [
        'af' => 'Hierdie lied is nie in Afrikaans beskikbaar nie. Die Engelse weergawe word gewys.',
        'en' => 'This song is not available in Afrikaans. Showing English version.',
        'zu' => 'Le ngoma ayitholakali ngesi-Afrikaans. Kuboniswa inguqulo yesiNgisi.',
        'xh' => 'Le ngoma ayifumaneki ngesi-Afrikaans. Kuboniswa inguqulelo yesiNgesi.',
        'pt' => 'Esta canção não está disponível em Africâner. Mostrando versão em Inglês.',
        'st' => 'Sefela sena ha se fumaneha ka Seafrikanse. Ho bontšoa phetolelo ea Senyesemane.'
    ],
    'song_not_in_english' => [
        'af' => 'Hierdie lied is nie in Engels beskikbaar nie. Die Afrikaanse weergawe word gewys.',
        'en' => 'This song is not available in English. Showing Afrikaans version.',
        'zu' => 'Le ngoma ayitholakali ngesiNgisi. Kuboniswa inguqulo yesi-Afrikaans.',
        'xh' => 'Le ngoma ayifumaneki ngesiNgesi. Kuboniswa inguqulelo yesi-Afrikaans.',
        'pt' => 'Esta canção não está disponível em Inglês. Mostrando versão em Africâner.',
        'st' => 'Sefela sena ha se fumaneha ka Senyesemane. Ho bontšoa phetolelo ea Seafrikanse.'
    ],
    'se_prev' => [
        'af' => 'Vorige',
        'en' => 'Previous',
        'zu' => 'Okwedlule',
        'xh' => 'Okwangaphambili',
        'pt' => 'Anterior',
        'st' => 'E fetileng'
    ],
    'se_next' => [
        'af' => 'Volgende',
        'en' => 'Next',
        'zu' => 'Okulandelayo',
        'xh' => 'Okulandelayo',
        'pt' => 'Seguinte',
        'st' => 'E latelang'
    ],
    'se_text_size' => [
        'af' => 'Teksgrootte',
        'en' => 'Text size',
        'zu' => 'Usayizi wombhalo',
        'xh' => 'Ubungakanani bombhalo',
        'pt' => 'Tamanho do texto',
        'st' => 'Boholo ba mongolo'
    ],
    'se_song_language' => [
        'af' => 'Taal van die lied',
        'en' => 'Language of the hymn',
        'zu' => 'Ulimi lwehubo',
        'xh' => 'Ulwimi lweculo',
        'pt' => 'Idioma do hino',
        'st' => 'Puo ea sefela'
    ],

    // ==================== AI SLIMBYBEL PAGE ====================
    'ai_slimbybel' => [
        'af' => 'AI Slimbybel',
        'en' => 'AI Smart Bible',
        'zu' => 'I-AI Slimbybel',
        'xh' => 'I-AI Slimbybel',
        'pt' => 'IA Bíblia Inteligente',
        'st' => 'Bibele e Bohlale ea AI'
    ],
    'spiritual_wisdom_ai' => [
        'af' => 'Geestelike Wysheid deur Kunsmatige Intelligensie',
        'en' => 'Spiritual Wisdom through Artificial Intelligence',
        'zu' => 'Ukuhlakanipha Okungokomoya nge-AI',
        'xh' => 'Ubulumko Bokomoya nge-AI',
        'pt' => 'Sabedoria Espiritual através de Inteligência Artificial',
        'st' => 'Bohlale ba Moea ka Bohlale ba Maiketsetso'
    ],
    'ask_scripture' => [
        'af' => 'Vra die Skrif',
        'en' => 'Ask the Scripture',
        'zu' => 'Buza Izibhalo',
        'xh' => 'Buza Izibhalo',
        'pt' => 'Pergunte às Escrituras',
        'st' => 'Botsa Mangolo'
    ],
    'ask_placeholder' => [
        'af' => 'bv. Wat beteken water in die Bybel?',
        'en' => 'e.g. What does water mean in the Bible?',
        'zu' => 'isb. Amanzi asho ukuthini eBhayibhelini?',
        'xh' => 'umz. Amanzi athetha ukuthini eBhayibhileni?',
        'pt' => 'ex. O que significa água na Bíblia?',
        'st' => 'mohl. Metsi a bolelang Bibeleng?'
    ],
    'explain' => [
        'af' => 'Verduidelik',
        'en' => 'Explain',
        'zu' => 'Chaza',
        'xh' => 'Cacisa',
        'pt' => 'Explicar',
        'st' => 'Hlalosa'
    ],
    'spiritual_interpretation_help' => [
        'af' => 'Geestelike uitleg • Afrikaans 1933/1953 • Skrif-met-Skrif',
        'en' => 'Spiritual interpretation • KJV • Scripture with Scripture',
        'zu' => 'Incazelo engokomoya • KJV • Izibhalo ngezibhalo',
        'xh' => 'Inkcazelo engokomoya • KJV • Izibhalo ngezibhalo',
        'pt' => 'Interpretação espiritual • ARC • Escritura com Escritura',
        'st' => 'Tlhaloso ea moea • 1933/1953 • Mangolo ka Mangolo'
    ],
    'answer' => [
        'af' => 'Antwoord',
        'en' => 'Answer',
        'zu' => 'Impendulo',
        'xh' => 'Impendulo',
        'pt' => 'Resposta',
        'st' => 'Karabo'
    ],
    'answer_placeholder' => [
        'af' => 'Jou antwoord sal hier in real-time verskyn...',
        'en' => 'Your answer will stream here in real time...',
        'zu' => 'Impendulo yakho izovela lapha ngesikhathi sangempela...',
        'xh' => 'Impendulo yakho iya kuvela apha ngexesha lokwenyani...',
        'pt' => 'Sua resposta aparecerá aqui em tempo real...',
        'st' => 'Karabo ea hao e tla hlaha mona ka nako ea nnete...'
    ],
    'example_questions' => [
        'af' => 'Voorbeeldvrae',
        'en' => 'Example Questions',
        'zu' => 'Imibuzo Yesibonelo',
        'xh' => 'Imibuzo Yomzekelo',
        'pt' => 'Perguntas de Exemplo',
        'st' => 'Lipotso tsa Mohlala'
    ],
    'empty_question' => [
        'af' => 'Leë vraag.',
        'en' => 'Empty question.',
        'zu' => 'Umbuzo ongenalutho.',
        'xh' => 'Umbuzo ongenanto.',
        'pt' => 'Pergunta vazia.',
        'st' => 'Potso e se nang letho.'
    ],
    'missing_api_key' => [
        'af' => 'Geen OPENAI_API_KEY gevind nie.',
        'en' => 'Missing OPENAI_API_KEY.',
        'zu' => 'I-OPENAI_API_KEY ayitholakali.',
        'xh' => 'I-OPENAI_API_KEY ayifumaneki.',
        'pt' => 'OPENAI_API_KEY não encontrada.',
        'st' => 'OPENAI_API_KEY ha e fumaneha.'
    ],
    'water_question_full' => [
        'af' => 'Wat beteken water in die Bybel?',
        'en' => 'What does water mean in the Bible?',
        'zu' => 'Amanzi asho ukuthini eBhayibhelini?',
        'xh' => 'Amanzi athetha ukuthini eBhayibhileni?',
        'pt' => 'O que significa água na Bíblia?',
        'st' => 'Metsi a bolelang Bibeleng?'
    ],
    'water_question_short' => [
        'af' => 'Wat beteken water?',
        'en' => 'What does water mean?',
        'zu' => 'Amanzi asho ukuthini?',
        'xh' => 'Amanzi athetha ukuthini?',
        'pt' => 'O que significa água?',
        'st' => 'Metsi a bolelang?'
    ],
    'sower_question_full' => [
        'af' => 'Verduidelik die gelykenis van die saaier',
        'en' => 'Explain the parable of the sower',
        'zu' => 'Chaza umfanekiso womhlwanyeli',
        'xh' => 'Cacisa umzekeliso womhlwayeli',
        'pt' => 'Explique a parábola do semeador',
        'st' => 'Hlalosa setšoantšo sa mojali'
    ],
    'sower_question_short' => [
        'af' => 'Gelykenis van die saaier',
        'en' => 'Parable of the sower',
        'zu' => 'Umfanekiso womhlwanyeli',
        'xh' => 'Umzekeliso womhlwayeli',
        'pt' => 'Parábola do semeador',
        'st' => 'Setšoantšo sa mojali'
    ],
    'spirit_question_full' => [
        'af' => 'Wat is die vrug van die Gees?',
        'en' => 'What is the fruit of the Spirit?',
        'zu' => 'Yini isithelo sikaMoya?',
        'xh' => 'Yintoni isiqhamo soMoya?',
        'pt' => 'Qual é o fruto do Espírito?',
        'st' => 'Ke eng setholoana sa Moea?'
    ],
    'spirit_question_short' => [
        'af' => 'Vrug van die Gees',
        'en' => 'Fruit of the Spirit',
        'zu' => 'Isithelo sikaMoya',
        'xh' => 'Isiqhamo soMoya',
        'pt' => 'Fruto do Espírito',
        'st' => 'Setholoana sa Moea'
    ],
    'kingdom_question_full' => [
        'af' => 'Verduidelik die Koninkryk van God',
        'en' => 'Explain the Kingdom of God',
        'zu' => 'Chaza uMbuso kaNkulunkulu',
        'xh' => 'Cacisa uBukumkani bukaThixo',
        'pt' => 'Explique o Reino de Deus',
        'st' => 'Hlalosa Mmuso oa Molimo'
    ],
    'kingdom_question_short' => [
        'af' => 'Koninkryk van God',
        'en' => 'Kingdom of God',
        'zu' => 'UMbuso kaNkulunkulu',
        'xh' => 'UBukumkani bukaThixo',
        'pt' => 'Reino de Deus',
        'st' => 'Mmuso oa Molimo'
    ],
    'sb_ai_note' => [
        'af' => 'Antwoorde word deur KI (OpenAI) geskryf. Toets alles aan die Skrif.',
        'en' => 'Answers are written by AI (OpenAI). Test everything against Scripture.',
        'zu' => 'Izimpendulo zibhalwa yi-AI (OpenAI). Hlola konke ngeZibhalo.',
        'xh' => 'Iimpendulo zibhalwa yi-AI (OpenAI). Vavanya yonke into ngeZibhalo.',
        'pt' => 'As respostas são escritas por IA (OpenAI). Examine tudo à luz das Escrituras.',
        'st' => 'Likarabo li ngoloa ke AI (OpenAI). Lekola tsohle ka Mangolo.'
    ],
    'sb_error' => [
        'af' => 'Kon nie \'n antwoord kry nie. Probeer asseblief weer.',
        'en' => 'Could not get an answer. Please try again.',
        'zu' => 'Akukwazekanga ukuthola impendulo. Sicela uzame futhi.',
        'xh' => 'Akukwazekanga ukufumana impendulo. Nceda uzame kwakhona.',
        'pt' => 'Não foi possível obter uma resposta. Tente novamente.',
        'st' => 'Ha ho a khoneha ho fumana karabo. Ka kopo leka hape.'
    ],
    'sb_cut_off' => [
        'af' => 'Die antwoord is onderbreek.',
        'en' => 'The answer was cut off.',
        'zu' => 'Impendulo inqamukile.',
        'xh' => 'Impendulo iqhawukile.',
        'pt' => 'A resposta foi interrompida.',
        'st' => 'Karabo e khaohile.'
    ],
    'sb_err_rate' => [
        'af' => 'Jy het baie vrae kort na mekaar gevra. Wag asseblief \'n paar minute en probeer dan weer.',
        'en' => 'You have asked many questions in a short time. Please wait a few minutes and try again.',
        'zu' => 'Ubuze imibuzo eminingi ngesikhathi esifushane. Sicela ulinde imizuzu embalwa bese uzama futhi.',
        'xh' => 'Ubuze imibuzo emininzi ngexesha elifutshane. Nceda ulinde imizuzu embalwa uze uzame kwakhona.',
        'pt' => 'Fez muitas perguntas em pouco tempo. Aguarde alguns minutos e tente novamente.',
        'st' => 'U botsitse lipotso tse ngata ka nako e khutšoane. Ka kopo ema metsotso e seng mekae ebe u leka hape.'
    ],
    'sb_stop' => [
        'af' => 'Stop',
        'en' => 'Stop',
        'zu' => 'Misa',
        'xh' => 'Yeka',
        'pt' => 'Parar',
        'st' => 'Emisa'
    ],
    'sb_new_question' => [
        'af' => 'Nuwe vraag',
        'en' => 'New question',
        'zu' => 'Umbuzo omusha',
        'xh' => 'Umbuzo omtsha',
        'pt' => 'Nova pergunta',
        'st' => 'Potso e ncha'
    ],

    // =====================================================================
    // NOTIFICATION TRANSLATIONS
    // =====================================================================

    // Admin notifications
    'notif_account_approved' => [
        'af' => '✅ Account Goedgekeur',
        'en' => '✅ Account Approved',
        'zu' => '✅ I-akhawunti Yamukelwe',
        'xh' => '✅ I-akhawunti Yamkelwe',
        'pt' => '✅ Conta Aprovada',
        'st' => '✅ Akhaonto e Amohetsoe'
    ],
    'notif_account_approved_msg' => [
        'af' => 'Jou rekening is goedgekeur. Jy kan nou aanmeld.',
        'en' => 'Your account has been approved. You can now log in.',
        'zu' => 'I-akhawunti yakho yamukelwe. Ungangena manje.',
        'xh' => 'I-akhawunti yakho yamkelwe. Ungangena ngoku.',
        'pt' => 'Sua conta foi aprovada. Você pode entrar agora.',
        'st' => 'Akhaonto ea hao e amohetsoe. O ka kena joale.'
    ],
    'notif_account_rejected' => [
        'af' => '❌ Account Afgekeur',
        'en' => '❌ Account Rejected',
        'zu' => '❌ I-akhawunti Yenqatshiwe',
        'xh' => '❌ I-akhawunti Yaliwe',
        'pt' => '❌ Conta Rejeitada',
        'st' => '❌ Akhaonto e Haniloe'
    ],
    'notif_account_rejected_msg' => [
        'af' => 'Jou rekening is afgekeur. Kontak admin vir meer inligting.',
        'en' => 'Your account has been rejected. Contact admin for more information.',
        'zu' => 'I-akhawunti yakho yenqatshiwe. Xhumana nomphathi ukuze uthole ulwazi olwengeziwe.',
        'xh' => 'I-akhawunti yakho yaliwe. Qhagamshelana nomlawuli ukufumana ulwazi oluthe kratya.',
        'pt' => 'Sua conta foi rejeitada. Entre em contato com o administrador para mais informações.',
        'st' => 'Akhaonto ea hao e haniloe. Ikopanye le motsamaisi bakeng sa lintlha tse ling.'
    ],
    'notif_pending_approval' => [
        'af' => '👤 Nuwe Goedkeuring',
        'en' => '👤 New Approval',
        'zu' => '👤 Ukugunyazwa Okusha',
        'xh' => '👤 Ukuvunywa Okutsha',
        'pt' => '👤 Nova Aprovação',
        'st' => '👤 Tumello e Ncha'
    ],
    'notif_pending_approval_msg' => [
        'af' => '{name} wag vir goedkeuring.',
        'en' => '{name} is waiting for approval.',
        'zu' => '{name} ulindele ukugunyazwa.',
        'xh' => '{name} ulindele ukuvunywa.',
        'pt' => '{name} está aguardando aprovação.',
        'st' => '{name} o emetse tumello.'
    ],
    'notif_spouse_request' => [
        'af' => '💍 Eggenoot Versoek',
        'en' => '💍 Spouse Request',
        'zu' => '💍 Isicelo Somngani',
        'xh' => '💍 Isicelo Somlingani',
        'pt' => '💍 Solicitação de Cônjuge',
        'st' => '💍 Kopo ea Molekane'
    ],
    'notif_spouse_request_msg' => [
        'af' => '{name} wil met jou as eggenoot koppel.',
        'en' => '{name} wants to link with you as spouse.',
        'zu' => '{name} ufuna ukuxhumana nawe njengomngani.',
        'xh' => '{name} ufuna ukudibanisa nawe njengomlingani.',
        'pt' => '{name} quer se conectar com você como cônjuge.',
        'st' => '{name} o batla ho hokahana le uena e le molekane.'
    ],
    'notif_spouse_accepted' => [
        'af' => '💕 Eggenoot Gekoppel',
        'en' => '💕 Spouse Linked',
        'zu' => '💕 Umngani Uxhunyiwe',
        'xh' => '💕 Umlingani Udibanisiwe',
        'pt' => '💕 Cônjuge Vinculado',
        'st' => '💕 Molekane o Hokahane'
    ],
    'notif_spouse_accepted_msg' => [
        'af' => '{name} het jou versoek aanvaar!',
        'en' => '{name} accepted your request!',
        'zu' => '{name} wamukele isicelo sakho!',
        'xh' => '{name} wamkele isicelo sakho!',
        'pt' => '{name} aceitou seu pedido!',
        'st' => '{name} o amohetsoe kopo ea hao!'
    ],
    'notif_spouse_rejected' => [
        'af' => '💔 Eggenoot Versoek Afgekeur',
        'en' => '💔 Spouse Request Declined',
        'zu' => '💔 Isicelo Somngani Senqatshiwe',
        'xh' => '💔 Isicelo Somlingani Saliwe',
        'pt' => '💔 Solicitação de Cônjuge Recusada',
        'st' => '💔 Kopo ea Molekane e Haniloe'
    ],
    'notif_spouse_rejected_msg' => [
        'af' => '{name} het jou eggenoot versoek afgekeur.',
        'en' => '{name} declined your spouse request.',
        'zu' => '{name} wenqabile isicelo sakho somngani.',
        'xh' => '{name} walikhaba isicelo sakho somlingani.',
        'pt' => '{name} recusou seu pedido de cônjuge.',
        'st' => '{name} o hanne kopo ea hao ea molekane.'
    ],
    'notif_appointment_request' => [
        'af' => '📅 Afspraak Versoek',
        'en' => '📅 Appointment Request',
        'zu' => '📅 Isicelo Sesikhathi',
        'xh' => '📅 Isicelo Sokuhlangana',
        'pt' => '📅 Solicitação de Compromisso',
        'st' => '📅 Kopo ea Kopano'
    ],
    'notif_appointment_request_msg' => [
        'af' => "{name} wil 'n afspraak maak op {date} om {time}.",
        'en' => '{name} wants to make an appointment on {date} at {time}.',
        'zu' => '{name} ufuna ukwenza isithembiso ngo-{date} ngo-{time}.',
        'xh' => '{name} ufuna ukwenza isigqibo ngo-{date} ngo-{time}.',
        'pt' => '{name} quer marcar um compromisso em {date} às {time}.',
        'st' => '{name} o batla ho etsa kopano ka {date} ka {time}.'
    ],
    'notif_appointment_confirmed' => [
        'af' => '✅ Afspraak Bevestig',
        'en' => '✅ Appointment Confirmed',
        'zu' => '✅ Isikhathi Siqinisekisiwe',
        'xh' => '✅ Ukuhlangana Kuqinisekisiwe',
        'pt' => '✅ Compromisso Confirmado',
        'st' => '✅ Kopano e Netefatsoe'
    ],
    'notif_appointment_confirmed_msg' => [
        'af' => 'Jou afspraak is bevestig!',
        'en' => 'Your appointment has been confirmed!',
        'zu' => 'Isithembiso sakho siqinisekisiwe!',
        'xh' => 'Isigqibo sakho siqinisekisiwe!',
        'pt' => 'Seu compromisso foi confirmado!',
        'st' => 'Kopano ea hao e netefatsoe!'
    ],
    'notif_appointment_cancelled' => [
        'af' => '❌ Afspraak Gekanselleer',
        'en' => '❌ Appointment Cancelled',
        'zu' => '❌ Isithembiso Sikhanseliwe',
        'xh' => '❌ Ukuhlangana Kurhoxisiwe',
        'pt' => '❌ Compromisso Cancelado',
        'st' => '❌ Kopano e Hlakotsoe'
    ],
    'notif_appointment_cancelled_msg' => [
        'af' => '{name} het jou afspraak op {date} om {time} gekanselleer.',
        'en' => '{name} cancelled your appointment on {date} at {time}.',
        'zu' => '{name} ukhanselile isithembiso sakho ngo-{date} ngo-{time}.',
        'xh' => '{name} urhoxisile ukuhlangana kwakho ngo-{date} ngo-{time}.',
        'pt' => '{name} cancelou seu compromisso em {date} às {time}.',
        'st' => '{name} o hlakotsoe kopano ea hao ka {date} ka {time}.'
    ],
    'notif_ampte_change' => [
        'af' => '⭐ Amp Verander',
        'en' => '⭐ Office Changed',
        'zu' => '⭐ Isikhundla Sishintshiwe',
        'xh' => '⭐ Isikhundla Sitshintshiwe',
        'pt' => '⭐ Cargo Alterado',
        'st' => '⭐ Kantoro e Fetohile'
    ],
    'notif_ampte_change_msg' => [
        'af' => 'Jou amp is verander na: {amp}',
        'en' => 'Your office has been changed to: {amp}',
        'zu' => 'Isikhundla sakho sishintshwe saba: {amp}',
        'xh' => 'Isikhundla sakho sitshintshwe saba: {amp}',
        'pt' => 'Seu cargo foi alterado para: {amp}',
        'st' => 'Kantoro ea hao e fetohile ho: {amp}'
    ],

    // Gospel notifications
    'notif_new_post' => [
        'af' => '✍️ Nuwe Plasing',
        'en' => '✍️ New Post',
        'zu' => '✍️ Okutsha Okufakiwe',
        'xh' => '✍️ Okutsha Okufakiweyo',
        'pt' => '✍️ Nova Publicação',
        'st' => '✍️ Poso e Ncha'
    ],
    'notif_new_post_msg' => [
        'af' => '{name} het in {room} geplaas.',
        'en' => '{name} posted in {room}.',
        'zu' => '{name} ufake ku-{room}.',
        'xh' => '{name} ufake ku-{room}.',
        'pt' => '{name} publicou em {room}.',
        'st' => '{name} o positse ho {room}.'
    ],
    'notif_new_comment' => [
        'af' => '💬 Nuwe Kommentaar',
        'en' => '💬 New Comment',
        'zu' => '💬 Ukuphawula Okusha',
        'xh' => '💬 Ukuphawula Okutsha',
        'pt' => '💬 Novo Comentário',
        'st' => '💬 Maikutlo a Matjha'
    ],
    'notif_new_comment_msg' => [
        'af' => '{name} het op jou plasing gereageer.',
        'en' => '{name} commented on your post.',
        'zu' => '{name} uphawule okutsha sakho.',
        'xh' => '{name} uphawule okutsha sakho.',
        'pt' => '{name} comentou na sua publicação.',
        'st' => '{name} o phehile maikutlo posong ea hao.'
    ],
    'notif_reaction' => [
        'af' => '{emoji} Reaksie',
        'en' => '{emoji} Reaction',
        'zu' => '{emoji} Ukusabela',
        'xh' => '{emoji} Ukusabela',
        'pt' => '{emoji} Reação',
        'st' => '{emoji} Karabelo'
    ],
    'notif_reaction_msg' => [
        'af' => '{name} het {emoji} op jou plasing gegee.',
        'en' => '{name} reacted with {emoji} to your post.',
        'zu' => '{name} usabele ngo-{emoji} ekutsha kwakho.',
        'xh' => '{name} usabele nge-{emoji} ekutsha kwakho.',
        'pt' => '{name} reagiu com {emoji} à sua publicação.',
        'st' => '{name} o arabile ka {emoji} posong ea hao.'
    ],
    'notif_comment_reaction_msg' => [
        'af' => '{name} het {emoji} op jou kommentaar gegee.',
        'en' => '{name} reacted with {emoji} to your comment.',
        'zu' => '{name} usabele ngo-{emoji} kumbono wakho.',
        'xh' => '{name} usabele nge-{emoji} kuluvo lwakho.',
        'pt' => '{name} reagiu com {emoji} ao seu comentário.',
        'st' => '{name} o arabile ka {emoji} maikutlong a hao.'
    ],
    'notif_tagged' => [
        'af' => '🏷️ Ge-tag',
        'en' => '🏷️ Tagged',
        'zu' => '🏷️ Umakiwe',
        'xh' => '🏷️ Umakiwe',
        'pt' => '🏷️ Marcado',
        'st' => '🏷️ O Tšoailoe'
    ],
    'notif_tagged_msg' => [
        'af' => "{name} het jou in 'n plasing ge-tag.",
        'en' => '{name} tagged you in a post.',
        'zu' => '{name} ukumake okutsha.',
        'xh' => '{name} ukumake okutsha.',
        'pt' => '{name} marcou você em uma publicação.',
        'st' => '{name} o o tšoaile posong.'
    ],

    // Calendar notifications
    'notif_event_reminder' => [
        'af' => '⏰ Herinnering',
        'en' => '⏰ Reminder',
        'zu' => '⏰ Isikhumbuzo',
        'xh' => '⏰ Isikhumbuzo',
        'pt' => '⏰ Lembrete',
        'st' => '⏰ Khopotso'
    ],
    'notif_event_reminder_msg' => [
        'af' => '{event} begin oor 30 minute om {time}',
        'en' => '{event} starts in 30 minutes at {time}',
        'zu' => '{event} iqala ngemizuzu engu-30 ngo-{time}',
        'xh' => '{event} iqala ngemizuzu engama-30 ngo-{time}',
        'pt' => '{event} começa em 30 minutos às {time}',
        'st' => '{event} e qala ka metsotso e 30 ka {time}'
    ],
    'notif_event_shared' => [
        'af' => '📅 Aktiwiteit Gedeel',
        'en' => '📅 Activity Shared',
        'zu' => '📅 Umsebenzi Wabelwe',
        'xh' => '📅 Umsebenzi Wabelwe',
        'pt' => '📅 Atividade Compartilhada',
        'st' => '📅 Mosebetsi o Arolelitsoe'
    ],
    'notif_event_shared_msg' => [
        'af' => "{name} het '{event}' met jou gedeel.",
        'en' => "{name} shared '{event}' with you.",
        'zu' => "{name} wabelane '{event}' nawe.",
        'xh' => "{name} wabelane '{event}' nawe.",
        'pt' => "{name} compartilhou '{event}' com você.",
        'st' => '{name} o arolelane \'{event}\' le uena.'
    ],
    'notif_diary_reminder' => [
        'af' => '📓 Dagboek Herinnering',
        'en' => '📓 Diary Reminder',
        'zu' => '📓 Isikhumbuzo Sedayari',
        'xh' => '📓 Isikhumbuzo Sedayari',
        'pt' => '📓 Lembrete do Diário',
        'st' => '📓 Khopotso ea Buka ea Letsatsi'
    ],
    'notif_diary_reminder_msg' => [
        'af' => 'Onthou om jou dagboek vandag by te werk!',
        'en' => 'Remember to update your diary today!',
        'zu' => 'Khumbula ukuvuselela idayari yakho namhlanje!',
        'xh' => 'Khumbula ukuhlaziya idayari yakho namhlanje!',
        'pt' => 'Lembre-se de atualizar seu diário hoje!',
        'st' => 'Hopola ho ntlafatsa buka ea hao ea letsatsi kajeno!'
    ],
    'notif_visit_scheduled' => [
        'af' => '🏠 Besoek Geskeduleer',
        'en' => '🏠 Visit Scheduled',
        'zu' => '🏠 Ukuvakasha Kuhleliwe',
        'xh' => '🏠 Utyelelo Lucwangcisiwe',
        'pt' => '🏠 Visita Agendada',
        'st' => '🏠 Ketelo e Reriloeng'
    ],
    'notif_visit_scheduled_msg' => [
        'af' => 'Besoek by {name} op {date}',
        'en' => 'Visit to {name} on {date}',
        'zu' => 'Ukuvakasha ku-{name} ngo-{date}',
        'xh' => 'Utyelelo ku-{name} ngo-{date}',
        'pt' => 'Visita a {name} em {date}',
        'st' => 'Ketelo ho {name} ka {date}'
    ],

    // Generic notification placeholders
    'notif_someone' => [
        'af' => 'Iemand',
        'en' => 'Someone',
        'zu' => 'Othile',
        'xh' => 'Omnye umntu',
        'pt' => 'Alguém',
        'st' => 'Motho e mong'
    ],

    // ==================== BIRTHDAY NOTIFICATIONS ====================
    'happy_birthday' => [
        'af' => 'Gelukkige Verjaarsdag!',
        'en' => 'Happy Birthday!',
        'zu' => 'Usuku lokuzalwa oluhle!',
        'xh' => 'Usuku lokuzalwa olumnandi!',
        'pt' => 'Feliz Aniversário!',
        'st' => 'Letsatsi la Tsoalo le Monate!'
    ],
    'birthday_self_msg' => [
        'af' => 'Baie geluk met jou verjaarsdag! Mag die Here jou seën.',
        'en' => 'Congratulations on your birthday! May God bless you.',
        'zu' => 'Siyakuhalalisela ngosuku lwakho lokuzalwa! Sengathi uNkulunkulu angakubusisa.',
        'xh' => 'Sivuyisana nawe ngosuku lwakho lokuzalwa! Ngamana uThixo akusikelele.',
        'pt' => 'Parabéns pelo seu aniversário! Que Deus te abençoe.',
        'st' => 'Re o lakaletsa letsatsi la tsoalo le monate! Molimo a o hlohonolofatse.'
    ],
    'birthday_notification' => [
        'af' => 'Verjaarsdag Vandag',
        'en' => 'Birthday Today',
        'zu' => 'Usuku Lokuzalwa Namhlanje',
        'xh' => 'Usuku Lokuzalwa Namhlanje',
        'pt' => 'Aniversário Hoje',
        'st' => 'Letsatsi la Tsoalo Kajeno'
    ],
    'birthday_notification_msg' => [
        'af' => '{name} vier vandag verjaarsdag!',
        'en' => '{name} is celebrating their birthday today!',
        'zu' => '{name} ugubha usuku lwakhe lokuzalwa namhlanje!',
        'xh' => '{name} ubhiyozela usuku lwakhe lokuzalwa namhlanje!',
        'pt' => '{name} está comemorando seu aniversário hoje!',
        'st' => '{name} o keteka letsatsi la hae la tsoalo kajeno!'
    ],

    // =====================================================================
    // JAVASCRIPT UI TRANSLATIONS (for gospel_media and prayers)
    // =====================================================================

    // Common actions
    'js_edit' => [
        'af' => 'Wysig',
        'en' => 'Edit',
        'zu' => 'Hlela',
        'xh' => 'Hlela',
        'pt' => 'Editar',
        'st' => 'Fetola'
    ],
    'js_delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Excluir',
        'st' => 'Hlakola'
    ],
    'js_cancel' => [
        'af' => 'Kanselleer',
        'en' => 'Cancel',
        'zu' => 'Khansela',
        'xh' => 'Rhoxisa',
        'pt' => 'Cancelar',
        'st' => 'Hlakola'
    ],
    'js_save' => [
        'af' => 'Stoor',
        'en' => 'Save',
        'zu' => 'Londoloza',
        'xh' => 'Gcina',
        'pt' => 'Salvar',
        'st' => 'Boloka'
    ],
    'js_saving' => [
        'af' => 'Stoor...',
        'en' => 'Saving...',
        'zu' => 'Ilondoloza...',
        'xh' => 'Igcina...',
        'pt' => 'Salvando...',
        'st' => 'E boloka...'
    ],
    'js_back' => [
        'af' => 'Terug',
        'en' => 'Back',
        'zu' => 'Emuva',
        'xh' => 'Emva',
        'pt' => 'Voltar',
        'st' => 'Morao'
    ],
    'js_error' => [
        'af' => 'Fout',
        'en' => 'Error',
        'zu' => 'Iphutha',
        'xh' => 'Impazamo',
        'pt' => 'Erro',
        'st' => 'Phoso'
    ],
    'js_loading' => [
        'af' => 'Laai...',
        'en' => 'Loading...',
        'zu' => 'Iyalayisha...',
        'xh' => 'Iyalayisha...',
        'pt' => 'Carregando...',
        'st' => 'E kenya...'
    ],

    // Post actions
    'js_post_actions' => [
        'af' => 'Post aksies',
        'en' => 'Post actions',
        'zu' => 'Izenzo zokuthunyelwe',
        'xh' => 'Izenzo zeposti',
        'pt' => 'Ações da postagem',
        'st' => 'Liketso tsa poso'
    ],
    'js_edit_post' => [
        'af' => 'Wysig plasing',
        'en' => 'Edit post',
        'zu' => 'Hlela okuthunyelwe',
        'xh' => 'Hlela iposti',
        'pt' => 'Editar postagem',
        'st' => 'Fetola poso'
    ],
    'js_confirm_delete' => [
        'af' => 'Bevestig verwydering',
        'en' => 'Confirm deletion',
        'zu' => 'Qinisekisa ukususa',
        'xh' => 'Qinisekisa ukucima',
        'pt' => 'Confirmar exclusão',
        'st' => 'Netefatsa ho hlakola'
    ],
    'js_cannot_undo' => [
        'af' => 'Hierdie aksie kan nie ontdoen word nie',
        'en' => 'This action cannot be undone',
        'zu' => 'Lesi senzo asikwazi ukuhlehliswa',
        'xh' => 'Esi senzo asinakubuyiselwa',
        'pt' => 'Esta ação não pode ser desfeita',
        'st' => 'Ketso ena e ke ke ea busetsoa morao'
    ],
    'js_deleting' => [
        'af' => 'Verwyder...',
        'en' => 'Deleting...',
        'zu' => 'Iyasusa...',
        'xh' => 'Iyacima...',
        'pt' => 'Excluindo...',
        'st' => 'E hlakola...'
    ],
    'js_post' => [
        'af' => 'Plaas',
        'en' => 'Post',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Postar',
        'st' => 'Posa'
    ],
    'js_posting' => [
        'af' => 'Plaas...',
        'en' => 'Posting...',
        'zu' => 'Iyathumela...',
        'xh' => 'Iyathumela...',
        'pt' => 'Postando...',
        'st' => 'E posa...'
    ],
    'js_type_message' => [
        'af' => 'Tik asseblief \'n boodskap',
        'en' => 'Please type a message',
        'zu' => 'Sicela ubhale umyalezo',
        'xh' => 'Nceda bhala umyalezo',
        'pt' => 'Por favor, digite uma mensagem',
        'st' => 'Ka kopo thaepa molaetsa'
    ],
    'js_error_posting' => [
        'af' => 'Fout met plaas',
        'en' => 'Error posting',
        'zu' => 'Iphutha lokuthumela',
        'xh' => 'Impazamo yokuthumela',
        'pt' => 'Erro ao postar',
        'st' => 'Phoso ea ho posa'
    ],
    'js_loading_posts' => [
        'af' => 'Laai plasings...',
        'en' => 'Loading posts...',
        'zu' => 'Ilayisha izithunyelwe...',
        'xh' => 'Ilayisha iiposti...',
        'pt' => 'Carregando postagens...',
        'st' => 'E kenya liposo...'
    ],
    'js_no_posts' => [
        'af' => 'Geen plasings in hierdie kamer nie',
        'en' => 'No posts in this room',
        'zu' => 'Akukho okuthunyelwe kuleli kamelo',
        'xh' => 'Akukho ziposti kule ndawo',
        'pt' => 'Nenhuma postagem nesta sala',
        'st' => 'Ha ho na liposo kamoreng ena'
    ],

    // Comments
    'js_comments' => [
        'af' => 'Kommentare',
        'en' => 'Comments',
        'zu' => 'Izimphawulo',
        'xh' => 'Izimvo',
        'pt' => 'Comentários',
        'st' => 'Maikutlo'
    ],
    'js_write_comment' => [
        'af' => 'Skryf \'n kommentaar...',
        'en' => 'Write a comment...',
        'zu' => 'Bhala ukuphawula...',
        'xh' => 'Bhala isimvo...',
        'pt' => 'Escreva um comentário...',
        'st' => 'Ngola maikutlo...'
    ],
    'js_no_comments' => [
        'af' => 'Geen kommentare nog nie',
        'en' => 'No comments yet',
        'zu' => 'Akukho zimphawulo okwamanje',
        'xh' => 'Akukho zimvo okwangoku',
        'pt' => 'Nenhum comentário ainda',
        'st' => 'Ha ho na maikutlo hajoale'
    ],
    'js_delete_comment' => [
        'af' => 'Verwyder hierdie kommentaar?',
        'en' => 'Delete this comment?',
        'zu' => 'Susa lesi simphawulo?',
        'xh' => 'Cima le simvo?',
        'pt' => 'Excluir este comentário?',
        'st' => 'Hlakola maikutlo ana?'
    ],
    'js_edit_comment' => [
        'af' => 'Wysig kommentaar:',
        'en' => 'Edit comment:',
        'zu' => 'Hlela ukuphawula:',
        'xh' => 'Hlela isimvo:',
        'pt' => 'Editar comentário:',
        'st' => 'Fetola maikutlo:'
    ],

    // Photos
    'js_add_photo' => [
        'af' => 'Voeg foto by',
        'en' => 'Add photo',
        'zu' => 'Engeza isithombe',
        'xh' => 'Yongeza umfanekiso',
        'pt' => 'Adicionar foto',
        'st' => 'Eketsa senepe'
    ],
    'js_remove_photo' => [
        'af' => 'Verwyder foto',
        'en' => 'Remove photo',
        'zu' => 'Susa isithombe',
        'xh' => 'Susa umfanekiso',
        'pt' => 'Remover foto',
        'st' => 'Tlosa senepe'
    ],
    'js_delete_photo' => [
        'af' => 'Verwyder hierdie foto?',
        'en' => 'Remove this photo?',
        'zu' => 'Susa lesi sithombe?',
        'xh' => 'Susa lo mfanekiso?',
        'pt' => 'Remover esta foto?',
        'st' => 'Tlosa senepe sena?'
    ],

    // Events
    'js_datetime' => [
        'af' => 'Datum/tyd',
        'en' => 'Date/time',
        'zu' => 'Usuku/isikhathi',
        'xh' => 'Umhla/ixesha',
        'pt' => 'Data/hora',
        'st' => 'Letsatsi/Nako'
    ],
    'js_place' => [
        'af' => 'Plek',
        'en' => 'Place',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local',
        'st' => 'Sebaka'
    ],

    // Prayers specific
    'js_share' => [
        'af' => 'Deel',
        'en' => 'Share',
        'zu' => 'Yabelana',
        'xh' => 'Yabelana',
        'pt' => 'Compartilhar',
        'st' => 'Arolelana'
    ],
    'js_update' => [
        'af' => 'Opdateer',
        'en' => 'Update',
        'zu' => 'Buyekeza',
        'xh' => 'Hlaziya',
        'pt' => 'Atualizar',
        'st' => 'Ntlafatsa'
    ],
    'js_choose_photo' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (okungenasidingo)',
        'xh' => 'Khetha umfanekiso (ayimfuneko)',
        'pt' => 'Escolher foto (opcional)',
        'st' => 'Khetha senepe (ka boithaopo)'
    ],
    'js_prayer_shared' => [
        'af' => 'Gebedsnood/getuienis gedeel!',
        'en' => 'Prayer/testimony shared!',
        'zu' => 'Umthandazo/ubufakazi buhlelwe!',
        'xh' => 'Umthandazo/ubungqina babelwe!',
        'pt' => 'Oração/testemunho compartilhado!',
        'st' => 'Thapelo/bopaki bo arolelitsoe!'
    ],
    'js_could_not_post' => [
        'af' => 'Kon nie plaas nie',
        'en' => 'Could not post',
        'zu' => 'Ayikwazanga ukuthumela',
        'xh' => 'Ayikwazanga ukuthumela',
        'pt' => 'Não foi possível postar',
        'st' => 'Ho hlolehile ho posa'
    ],
    'js_post_updated' => [
        'af' => 'Post opgedateer!',
        'en' => 'Post updated!',
        'zu' => 'Okuthunyelwe kubuyekeziwe!',
        'xh' => 'Iposti ihlaziyiwe!',
        'pt' => 'Postagem atualizada!',
        'st' => 'Poso e ntlafalitsoe!'
    ],
    'js_could_not_update' => [
        'af' => 'Kon nie opdateer nie',
        'en' => 'Could not update',
        'zu' => 'Ayikwazanga ukubuyekeza',
        'xh' => 'Ayikwazanga ukuhlaziya',
        'pt' => 'Não foi possível atualizar',
        'st' => 'Ho hlolehile ho ntlafatsa'
    ],
    'js_photo_removed' => [
        'af' => 'Foto verwyder!',
        'en' => 'Photo removed!',
        'zu' => 'Isithombe sisusiwe!',
        'xh' => 'Umfanekiso ususwe!',
        'pt' => 'Foto removida!',
        'st' => 'Senepe se tlositsoe!'
    ],
    'js_could_not_remove' => [
        'af' => 'Kon nie verwyder nie',
        'en' => 'Could not remove',
        'zu' => 'Ayikwazanga ukususa',
        'xh' => 'Ayikwazanga ukususa',
        'pt' => 'Não foi possível remover',
        'st' => 'Ho hlolehile ho tlosa'
    ],
    'js_delete_post' => [
        'af' => 'Wis hierdie post uit?',
        'en' => 'Delete this post?',
        'zu' => 'Susa le posti?',
        'xh' => 'Cima le posti?',
        'pt' => 'Excluir esta postagem?',
        'st' => 'Hlakola poso ena?'
    ],
    'js_post_deleted' => [
        'af' => 'Post uitgewis!',
        'en' => 'Post deleted!',
        'zu' => 'Okuthunyelwe kususiwe!',
        'xh' => 'Iposti icinyiwe!',
        'pt' => 'Postagem excluída!',
        'st' => 'Poso e hlakotsoe!'
    ],
    'js_could_not_delete' => [
        'af' => 'Kon nie uitwis nie',
        'en' => 'Could not delete',
        'zu' => 'Ayikwazanga ukususa',
        'xh' => 'Ayikwazanga ukucima',
        'pt' => 'Não foi possível excluir',
        'st' => 'Ho hlolehile ho hlakola'
    ],
    'js_loading_prayers' => [
        'af' => 'Laai gebede...',
        'en' => 'Loading prayers...',
        'zu' => 'Ilayisha imithandazo...',
        'xh' => 'Ilayisha imithandazo...',
        'pt' => 'Carregando orações...',
        'st' => 'E kenya lithapelo...'
    ],
    'js_no_prayers' => [
        'af' => 'Geen gebede/getuienisse gevind nie',
        'en' => 'No prayers/testimonies found',
        'zu' => 'Akukho mithandazo/ubufakazi okutholiwe',
        'xh' => 'Akukho mithandazo/ubungqina bufunyenweyo',
        'pt' => 'Nenhuma oração/testemunho encontrado',
        'st' => 'Ha ho na lithapelo/bopaki bo fumanoeng'
    ],
    'js_could_not_load' => [
        'af' => 'Kon nie laai nie',
        'en' => 'Could not load',
        'zu' => 'Ayikwazanga ukulayisha',
        'xh' => 'Ayikwazanga ukulayisha',
        'pt' => 'Não foi possível carregar',
        'st' => 'Ho hlolehile ho kenya'
    ],
    'js_prayer' => [
        'af' => 'Gebed',
        'en' => 'Prayer',
        'zu' => 'Umthandazo',
        'xh' => 'Umthandazo',
        'pt' => 'Oração',
        'st' => 'Thapelo'
    ],
    'js_testimony' => [
        'af' => 'Getuienis',
        'en' => 'Testimony',
        'zu' => 'Ubufakazi',
        'xh' => 'Ubungqina',
        'pt' => 'Testemunho',
        'st' => 'Bopaki'
    ],
    'js_comment_posted' => [
        'af' => 'Kommentaar geplaas!',
        'en' => 'Comment posted!',
        'zu' => 'Ukuphawula kuthunyelwe!',
        'xh' => 'Isimvo sithunyelelwe!',
        'pt' => 'Comentário postado!',
        'st' => 'Maikutlo a positsoe!'
    ],
    'js_comment_updated' => [
        'af' => 'Kommentaar opgedateer!',
        'en' => 'Comment updated!',
        'zu' => 'Ukuphawula kubuyekeziwe!',
        'xh' => 'Isimvo sihlaziyiwe!',
        'pt' => 'Comentário atualizado!',
        'st' => 'Maikutlo a ntlafalitsoe!'
    ],
    'js_comment_deleted' => [
        'af' => 'Kommentaar uitgewis!',
        'en' => 'Comment deleted!',
        'zu' => 'Ukuphawula kususiwe!',
        'xh' => 'Isimvo sicinyiwe!',
        'pt' => 'Comentário excluído!',
        'st' => 'Maikutlo a hlakotsoe!'
    ],
    'js_remove' => [
        'af' => 'Verwyder',
        'en' => 'Remove',
        'zu' => 'Susa',
        'xh' => 'Susa',
        'pt' => 'Remover',
        'st' => 'Tlosa'
    ],
    // Rooms menu translations
    'js_rooms' => [
        'af' => 'Kamers',
        'en' => 'Rooms',
        'zu' => 'Amagumbi',
        'xh' => 'Amagumbi',
        'pt' => 'Salas',
        'st' => 'Likamore'
    ],
    'js_manage_rooms' => [
        'af' => 'Bestuur Kamers',
        'en' => 'Manage Rooms',
        'zu' => 'Phatha Amagumbi',
        'xh' => 'Lawula Amagumbi',
        'pt' => 'Gerenciar Salas',
        'st' => 'Laola Likamore'
    ],
    'js_loading_rooms' => [
        'af' => 'Laai kamers…',
        'en' => 'Loading rooms…',
        'zu' => 'Iyalayisha amagumbi…',
        'xh' => 'Ilayisha amagumbi…',
        'pt' => 'Carregando salas…',
        'st' => 'E kenya likamore…'
    ],
    'js_no_rooms' => [
        'af' => 'Geen kamers nie',
        'en' => 'No rooms',
        'zu' => 'Awekho amagumbi',
        'xh' => 'Akukho magumbi',
        'pt' => 'Nenhuma sala',
        'st' => 'Ha ho na likamore'
    ],
    'js_automatic' => [
        'af' => 'Outomaties',
        'en' => 'Automatic',
        'zu' => 'Okuzenzakalelayo',
        'xh' => 'Ngokuzenzekelayo',
        'pt' => 'Automático',
        'st' => 'Ka bo eona'
    ],
    'js_auto' => [
        'af' => 'Outo',
        'en' => 'Auto',
        'zu' => 'Outo',
        'xh' => 'Outo',
        'pt' => 'Auto',
        'st' => 'Auto'
    ],
    'js_joined_section' => [
        'af' => 'Gekose',
        'en' => 'Joined',
        'zu' => 'Kujoyinwe',
        'xh' => 'Kujoyinwe',
        'pt' => 'Inscritas',
        'st' => 'O kene'
    ],
    'js_joined_badge' => [
        'af' => 'Gekies',
        'en' => 'Joined',
        'zu' => 'Kujoyinwe',
        'xh' => 'Kujoyinwe',
        'pt' => 'Inscrita',
        'st' => 'O kene'
    ],
    'js_reactions' => [
        'af' => 'Reaksies',
        'en' => 'Reactions',
        'zu' => 'Ukusabela',
        'xh' => 'Iimpendulo',
        'pt' => 'Reações',
        'st' => 'Likarabo'
    ],
    'js_no_reactions' => [
        'af' => 'Nog geen reaksies nie',
        'en' => 'No reactions yet',
        'zu' => 'Akukho ukusabela okwamanje',
        'xh' => 'Akukho zimpendulo okwangoku',
        'pt' => 'Ainda não há reações',
        'st' => 'Ha ho likarabo hajoale'
    ],
    'js_all' => [
        'af' => 'Almal',
        'en' => 'All',
        'zu' => 'Konke',
        'xh' => 'Zonke',
        'pt' => 'Todos',
        'st' => 'Tsohle'
    ],
    'js_new_posts' => [
        'af' => 'Nuwe plasings',
        'en' => 'New posts',
        'zu' => 'Okuthunyelwe okusha',
        'xh' => 'Izithuba ezintsha',
        'pt' => 'Novas publicações',
        'st' => 'Diphatlalatso tse ncha'
    ],
    'js_add_to_calendar' => [
        'af' => 'Voeg by kalender',
        'en' => 'Add to calendar',
        'zu' => 'Engeza kukhalenda',
        'xh' => 'Yongeza kwikhalenda',
        'pt' => 'Adicionar ao calendário',
        'st' => 'Kenya khalendareng'
    ],

    // DAILY THOUGHT TRANSLATIONS
    'thought_of_day' => [
        'af' => 'Gedagte van die Dag',
        'en' => 'Thought of the Day',
        'zu' => 'Umcabango Wosuku',
        'xh' => 'Ingcinga Yosuku',
        'pt' => 'Pensamento do Dia',
        'st' => 'Mohopolo oa Letsatsi'
    ],
    'daily_thought' => [
        'af' => 'Daaglikse Gedagte',
        'en' => 'Daily Thought',
        'zu' => 'Umcabango Wansuku Zonke',
        'xh' => 'Ingcinga Yemihla Ngemihla',
        'pt' => 'Pensamento Diário',
        'st' => 'Mohopolo oa Letsatsi le Letsatsi'
    ],
    'thought_content' => [
        'af' => 'Gedagte inhoud',
        'en' => 'Thought content',
        'zu' => 'Okuqukethwe komcabango',
        'xh' => 'Umxholo wengcinga',
        'pt' => 'Conteúdo do pensamento',
        'st' => 'Litaba tsa mohopolo'
    ],
    'thought_author' => [
        'af' => 'Outeur (opsioneel)',
        'en' => 'Author (optional)',
        'zu' => 'Umbhali (ukukhetha)',
        'xh' => 'Umbhali (ukhetho)',
        'pt' => 'Autor (opcional)',
        'st' => 'Mongoli (ka boithaopo)'
    ],
    'thought_date' => [
        'af' => 'Datum om te wys',
        'en' => 'Date to display',
        'zu' => 'Usuku lokubonisa',
        'xh' => 'Umhla wokubonisa',
        'pt' => 'Data para exibir',
        'st' => 'Letsatsi la ho bontša'
    ],
    'save_thought' => [
        'af' => 'Stoor Gedagte',
        'en' => 'Save Thought',
        'zu' => 'Gcina Umcabango',
        'xh' => 'Gcina Ingcinga',
        'pt' => 'Salvar Pensamento',
        'st' => 'Boloka Mohopolo'
    ],
    'thought_saved' => [
        'af' => 'Gedagte gestoor!',
        'en' => 'Thought saved!',
        'zu' => 'Umcabango ugcinwe!',
        'xh' => 'Ingcinga igcinwe!',
        'pt' => 'Pensamento salvo!',
        'st' => 'Mohopolo o bolokiloe!'
    ],
    'thought_deleted' => [
        'af' => 'Gedagte verwyder!',
        'en' => 'Thought deleted!',
        'zu' => 'Umcabango ususiwe!',
        'xh' => 'Ingcinga isusiwe!',
        'pt' => 'Pensamento excluído!',
        'st' => 'Mohopolo o hlakotsoe!'
    ],
    'no_thought_today' => [
        'af' => 'Geen gedagte vir vandag nie',
        'en' => 'No thought for today',
        'zu' => 'Akukho mcabango wanamuhla',
        'xh' => 'Akukho ngcinga yanamhlanje',
        'pt' => 'Nenhum pensamento para hoje',
        'st' => 'Ha ho na mohopolo oa kajeno'
    ],
    'manage_thoughts' => [
        'af' => 'Bestuur Daaglikse Gedagtes',
        'en' => 'Manage Daily Thoughts',
        'zu' => 'Phatha Imicabango Yansuku Zonke',
        'xh' => 'Lawula Iingcinga Zemihla Ngemihla',
        'pt' => 'Gerenciar Pensamentos Diários',
        'st' => 'Laola Mehopolo ea Letsatsi le Letsatsi'
    ],
    'upcoming_thoughts' => [
        'af' => 'Komende Gedagtes',
        'en' => 'Upcoming Thoughts',
        'zu' => 'Imicabango Ezayo',
        'xh' => 'Iingcinga Ezizayo',
        'pt' => 'Pensamentos Próximos',
        'st' => 'Mehopolo e Tlang'
    ],
    'no_upcoming' => [
        'af' => 'Geen komende gedagtes nie',
        'en' => 'No upcoming thoughts',
        'zu' => 'Akukho micabango ezayo',
        'xh' => 'Akukho ngcinga zizayo',
        'pt' => 'Nenhum pensamento próximo',
        'st' => 'Ha ho na mehopolo e tlang'
    ],
    'delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Excluir',
        'st' => 'Hlakola'
    ],
    'confirm_delete_thought' => [
        'af' => 'Is jy seker jy wil hierdie gedagte verwyder?',
        'en' => 'Are you sure you want to delete this thought?',
        'zu' => 'Uqinisekile ukuthi ufuna ukususa lo mcabango?',
        'xh' => 'Uqinisekile ukuba ufuna ukucima le ngcinga?',
        'pt' => 'Tem certeza de que deseja excluir este pensamento?',
        'st' => 'Na o na le bonnete ba hore o batla ho hlakola mohopolo ona?'
    ],
    'thought_time' => [
        'af' => 'Tyd om te wys',
        'en' => 'Time to display',
        'zu' => 'Isikhathi sokubonisa',
        'xh' => 'Ixesha lokubonisa',
        'pt' => 'Hora para exibir',
        'st' => 'Nako ea ho bontša'
    ],
    'notif_sent' => [
        'af' => 'Gestuur',
        'en' => 'Sent',
        'zu' => 'Ithunyelwe',
        'xh' => 'Ithunyelwe',
        'pt' => 'Enviado',
        'st' => 'E rometsoe'
    ],
    'notif_pending' => [
        'af' => 'Wag',
        'en' => 'Pending',
        'zu' => 'Ilindile',
        'xh' => 'Ilindile',
        'pt' => 'Pendente',
        'st' => 'E emetse'
    ],
    'thought_history' => [
        'af' => 'Gedagtes',
        'en' => 'Thoughts',
        'zu' => 'Imicabango',
        'xh' => 'Iingcinga',
        'pt' => 'Pensamentos',
        'st' => 'Mehopolo'
    ],
    'thought_history_title' => [
        'af' => 'Gedagtes van die Afgelope 30 Dae',
        'en' => 'Thoughts from the Past 30 Days',
        'zu' => 'Imicabango Yezinsuku Ezingu-30 Ezedlule',
        'xh' => 'Iingcinga Zeentsuku Ezingama-30 Ezidlulileyo',
        'pt' => 'Pensamentos dos Últimos 30 Dias',
        'st' => 'Mehopolo ea Matsatsi a 30 a Fetileng'
    ],
    'no_thoughts_yet' => [
        'af' => 'Nog geen gedagtes nie',
        'en' => 'No thoughts yet',
        'zu' => 'Akukho micabango okwamanje',
        'xh' => 'Akukho ngcinga okwangoku',
        'pt' => 'Nenhum pensamento ainda',
        'st' => 'Ha ho na mehopolo hajoale'
    ],
    'notif_thought_title' => [
        'af' => 'Gedagte van die Dag',
        'en' => 'Thought of the Day',
        'zu' => 'Umcabango Wosuku',
        'xh' => 'Ingcinga Yosuku',
        'pt' => 'Pensamento do Dia',
        'st' => 'Mohopolo oa Letsatsi'
    ],
    // Daily thought: language, spelling check and translations (admin)
    'thought_lang' => [
        'af' => 'Taal van die gedagte',
        'en' => 'Language of the thought',
        'zu' => 'Ulimi lomcabango',
        'xh' => 'Ulwimi lwengcinga',
        'pt' => 'Língua do pensamento',
        'st' => 'Puo ea mohopolo'
    ],
    'gd_check_btn' => [
        'af' => 'Kontroleer spelling',
        'en' => 'Check spelling',
        'zu' => 'Hlola ukupela',
        'xh' => 'Khangela upelo',
        'pt' => 'Verificar a ortografia',
        'st' => 'Hlahloba mopeleto'
    ],
    'gd_check_title' => [
        'af' => 'Voorgestelde verbeterings',
        'en' => 'Suggested corrections',
        'zu' => 'Izilungiso eziphakanyisiwe',
        'xh' => 'Izilungiso ezicetywayo',
        'pt' => 'Correções sugeridas',
        'st' => 'Litokiso tse sisintsoeng'
    ],
    'gd_check_use' => [
        'af' => 'Gebruik verbetering',
        'en' => 'Use correction',
        'zu' => 'Sebenzisa isilungiso',
        'xh' => 'Sebenzisa isilungiso',
        'pt' => 'Usar a correção',
        'st' => 'Sebelisa tokiso'
    ],
    'gd_check_keep' => [
        'af' => 'Hou my teks',
        'en' => 'Keep my text',
        'zu' => 'Gcina umbhalo wami',
        'xh' => 'Gcina umbhalo wam',
        'pt' => 'Manter o meu texto',
        'st' => 'Boloka mongolo oa ka'
    ],
    'gd_check_none' => [
        'af' => 'Geen foute gevind nie.',
        'en' => 'No mistakes found.',
        'zu' => 'Awekho amaphutha atholakele.',
        'xh' => 'Akukho zimpazamo zifunyenweyo.',
        'pt' => 'Não foram encontrados erros.',
        'st' => 'Ha ho liphoso tse fumanoeng.'
    ],
    'gd_check_failed' => [
        'af' => 'Kon nie nou die spelling kontroleer nie. Probeer weer.',
        'en' => 'Could not check the spelling right now. Please try again.',
        'zu' => 'Akukwazekanga ukuhlola ukupela manje. Sicela uzame futhi.',
        'xh' => 'Akukwazekanga ukukhangela upelo ngoku. Nceda uzame kwakhona.',
        'pt' => 'Não foi possível verificar a ortografia agora. Tente novamente.',
        'st' => 'Ha ho a khoneha ho hlahloba mopeleto hona joale. Ka kopo leka hape.'
    ],
    'gd_saving' => [
        'af' => 'Stoor en vertaal…',
        'en' => 'Saving and translating…',
        'zu' => 'Kuyagcinwa futhi kuyahunyushwa…',
        'xh' => 'Kuyagcinwa kwaye kuyaguqulelwa…',
        'pt' => 'A guardar e a traduzir…',
        'st' => 'E a boloka le ho fetolela…'
    ],
    'gd_saved_translated' => [
        'af' => 'Gedagte gestoor en in al die tale vertaal.',
        'en' => 'Thought saved and translated into every language.',
        'zu' => 'Umcabango ugciniwe futhi wahunyushelwa kuzo zonke izilimi.',
        'xh' => 'Ingcinga igciniwe yaza yaguqulelwa kuzo zonke iilwimi.',
        'pt' => 'Pensamento guardado e traduzido para todas as línguas.',
        'st' => 'Mohopolo o bolokiloe mme o fetoletsoe ka lipuo tsohle.'
    ],
    'gd_saved_missing' => [
        'af' => 'Gedagte gestoor, maar die vertaling het misluk vir: {langs}. Probeer weer in die lys hieronder.',
        'en' => 'Thought saved, but the translation failed for: {langs}. Try again in the list below.',
        'zu' => 'Umcabango ugciniwe, kodwa ukuhumusha kwehlulekile kulezi zilimi: {langs}. Zama futhi ohlwini olungezansi.',
        'xh' => 'Ingcinga igciniwe, kodwa ukuguqulela akuphumelelanga kwezi lwimi: {langs}. Zama kwakhona kuluhlu olungezantsi.',
        'pt' => 'Pensamento guardado, mas a tradução falhou para: {langs}. Tente novamente na lista abaixo.',
        'st' => 'Mohopolo o bolokiloe, empa phetolelo e hlolehile lipuong tsena: {langs}. Leka hape lethathamong le ka tlase.'
    ],
    'gd_translate_missing' => [
        'af' => 'Vertaal ontbrekende tale',
        'en' => 'Translate missing languages',
        'zu' => 'Humusha izilimi ezisele',
        'xh' => 'Guqulela iilwimi ezishiyekileyo',
        'pt' => 'Traduzir as línguas em falta',
        'st' => 'Fetolela lipuo tse setseng'
    ],
    'gd_translated' => [
        'af' => 'Vertalings bygewerk.',
        'en' => 'Translations updated.',
        'zu' => 'Ukuhumusha kubuyekeziwe.',
        'xh' => 'Iinguqulelo zihlaziyiwe.',
        'pt' => 'Traduções atualizadas.',
        'st' => 'Liphetolelo li ntlafalitsoe.'
    ],
    'gd_translate_failed' => [
        'af' => 'Kon nie nou vertaal nie. Probeer later weer.',
        'en' => 'Could not translate right now. Please try again later.',
        'zu' => 'Akukwazekanga ukuhumusha manje. Sicela uzame futhi emuva kwesikhashana.',
        'xh' => 'Akukwazekanga ukuguqulela ngoku. Nceda uzame kwakhona kamva.',
        'pt' => 'Não foi possível traduzir agora. Tente novamente mais tarde.',
        'st' => 'Ha ho a khoneha ho fetolela hona joale. Ka kopo leka hape hamorao.'
    ],
    'gd_original' => [
        'af' => 'Oorspronklik',
        'en' => 'Original',
        'zu' => 'Okwangempela',
        'xh' => 'Eyokuqala',
        'pt' => 'Original',
        'st' => 'Ea mantlha'
    ],
    'gd_edit_translation' => [
        'af' => 'Wysig vertaling',
        'en' => 'Edit translation',
        'zu' => 'Hlela ukuhumusha',
        'xh' => 'Hlela inguqulelo',
        'pt' => 'Editar a tradução',
        'st' => 'Fetola phetolelo'
    ],
    'gd_translation_saved' => [
        'af' => 'Vertaling gestoor.',
        'en' => 'Translation saved.',
        'zu' => 'Ukuhumusha kugciniwe.',
        'xh' => 'Inguqulelo igciniwe.',
        'pt' => 'Tradução guardada.',
        'st' => 'Phetolelo e bolokiloe.'
    ],
    'gd_by_hand' => [
        'af' => 'Met die hand reggemaak',
        'en' => 'Corrected by hand',
        'zu' => 'Kulungiswe ngesandla',
        'xh' => 'Ilungiswe ngesandla',
        'pt' => 'Corrigida à mão',
        'st' => 'E lokisitsoe ka letsoho'
    ],
    'gd_not_translated' => [
        'af' => 'Nog nie vertaal nie — lede in hierdie taal sien die oorspronklike.',
        'en' => 'Not translated yet — members in this language see the original.',
        'zu' => 'Akukahunyushwa — amalungu akulolu limi abona okwangempela.',
        'xh' => 'Ayikaguqulelwa — amalungu akolu lwimi abona eyokuqala.',
        'pt' => 'Ainda não traduzido — os membros nesta língua veem o original.',
        'st' => 'Ha e so fetoleloe — litho tsa puo ena li bona ea mantlha.'
    ],
    'gd_ai_off' => [
        'af' => 'Die vertaaldiens is nie opgestel nie (OpenAI-sleutel ontbreek).',
        'en' => 'The translation service is not set up (OpenAI key missing).',
        'zu' => 'Insiza yokuhumusha ayikahlelwa (ukhiye we-OpenAI awukho).',
        'xh' => 'Inkonzo yokuguqulela ayikalungiswa (isitshixo se-OpenAI asikho).',
        'pt' => 'O serviço de tradução não está configurado (falta a chave da OpenAI).',
        'st' => 'Tšebeletso ea phetolelo ha e so hlophisoe (senotlolo sa OpenAI ha se eo).'
    ],
    'gd_too_long' => [
        'af' => 'Die gedagte is te lank (hoogstens {n} karakters).',
        'en' => 'The thought is too long (at most {n} characters).',
        'zu' => 'Umcabango mude kakhulu (izinhlamvu ezingafika ku-{n}).',
        'xh' => 'Ingcinga inde kakhulu (iimpawu ezingafikelela kwi-{n}).',
        'pt' => 'O pensamento é demasiado longo (no máximo {n} caracteres).',
        'st' => 'Mohopolo o molelele haholo (litlhaku tse {n} ka ho fetisisa).'
    ],
    'adm_chars_over' => [
        'af' => '{n} karakters te veel',
        'en' => '{n} characters too many',
        'zu' => 'Izinhlamvu ezingu-{n} ngaphezu kwalokho okuvunyelwe',
        'xh' => 'Iimpawu ezi-{n} ngaphezu kokuvumelekileyo',
        'pt' => '{n} caracteres a mais',
        'st' => 'Litlhaku tse {n} ho feta tse lumelloang'
    ],
    'gd_langs_label' => [
        'af' => 'Tale',
        'en' => 'Languages',
        'zu' => 'Izilimi',
        'xh' => 'Iilwimi',
        'pt' => 'Línguas',
        'st' => 'Lipuo'
    ],

    // Bybel Opsomming (Bible Summary) module
    'bible_summary' => [
        'af' => 'Bybel Opsomming',
        'en' => 'Bible Summary',
        'zu' => 'Isifinyezo SeBhayibheli',
        'xh' => 'Isishwankathelo SeBhayibhile',
        'pt' => 'Resumo da Bíblia',
        'st' => 'Khutsufatso ea Bibele'
    ],
    'bible_summary_tagline' => [
        'af' => '’n Geestelike reis deur die Skrif',
        'en' => 'A spiritual journey through Scripture',
        'zu' => 'Uhambo lomoya eBhayibhelini',
        'xh' => 'Uhambo lomoya eBhayibhileni',
        'pt' => 'Uma jornada espiritual pelas Escrituras',
        'st' => 'Leeto la moea ka Mangolo'
    ],
    'sum_available' => [
        'af' => 'Beskikbaar',
        'en' => 'Available',
        'zu' => 'Iyatholakala',
        'xh' => 'Iyafumaneka',
        'pt' => 'Disponível',
        'st' => 'E fumaneha'
    ],
    'sum_coming_soon' => [
        'af' => 'Kom binnekort',
        'en' => 'Coming soon',
        'zu' => 'Iyeza maduze',
        'xh' => 'Iyeza kungekudala',
        'pt' => 'Em breve',
        'st' => 'E tla haufinyane'
    ],
    'sum_part' => [
        'af' => 'Deel',
        'en' => 'Part',
        'zu' => 'Ingxenye',
        'xh' => 'Inxalenye',
        'pt' => 'Parte',
        'st' => 'Karolo'
    ],
    'sum_parts' => [
        'af' => 'Dele',
        'en' => 'Parts',
        'zu' => 'Izingxenye',
        'xh' => 'Iinxalenye',
        'pt' => 'Partes',
        'st' => 'Likarolo'
    ],
    'sum_studies_label' => [
        'af' => 'studies',
        'en' => 'studies',
        'zu' => 'izifundo',
        'xh' => 'izifundo',
        'pt' => 'estudos',
        'st' => 'lithuto'
    ],
    'sum_study_label' => [
        'af' => 'Studie',
        'en' => 'Study',
        'zu' => 'Isifundo',
        'xh' => 'Isifundo',
        'pt' => 'Estudo',
        'st' => 'Thuto'
    ],
    'sum_the_story' => [
        'af' => 'Die verhaal',
        'en' => 'The story',
        'zu' => 'Indaba',
        'xh' => 'Ibali',
        'pt' => 'A história',
        'st' => 'Pale'
    ],
    'sum_spiritual_meaning' => [
        'af' => 'Kort geestelike verklaring',
        'en' => 'Short spiritual explanation',
        'zu' => 'Incazelo emfushane yokomoya',
        'xh' => 'Ingcaciso emfutshane yokomoya',
        'pt' => 'Breve explicação espiritual',
        'st' => 'Tlhaloso e khutšoanyane ea moea'
    ],
    'sum_scripture' => [
        'af' => 'Skrif',
        'en' => 'Scripture',
        'zu' => 'Umbhalo',
        'xh' => 'Isibhalo',
        'pt' => 'Escritura',
        'st' => 'Lengolo'
    ],
    'sum_application' => [
        'af' => 'Toepassing',
        'en' => 'Application',
        'zu' => 'Ukusetshenziswa',
        'xh' => 'Ukusetyenziswa',
        'pt' => 'Aplicação',
        'st' => 'Tšebeliso'
    ],
    'sum_about_doc' => [
        'af' => 'Oor hierdie leerstuk',
        'en' => 'About this teaching',
        'zu' => 'Mayelana nalesi sifundo',
        'xh' => 'Malunga nesi sifundo',
        'pt' => 'Sobre este ensino',
        'st' => 'Ka thuto ena'
    ],
    'sum_symbol_key' => [
        'af' => 'Sleutel van Simbole',
        'en' => 'Key of Symbols',
        'zu' => 'Ukhiye Wezimpawu',
        'xh' => 'Isitshixo Seempawu',
        'pt' => 'Chave de Símbolos',
        'st' => 'Senotlolo sa Matšoao'
    ],
    'sum_af_only' => [
        'af' => 'Hierdie opsomming is tans slegs in Afrikaans beskikbaar. Vertalings volg binnekort.',
        'en' => 'This summary is currently only available in Afrikaans. Translations are coming soon.',
        'zu' => 'Lesi sifinyezo sitholakala ngesiBhunu kuphela okwamanje. Ukuhunyushwa kuyeza maduze.',
        'xh' => 'Esi sishwankathelo sifumaneka ngesiBhulu kuphela okwangoku. Iinguqulelo ziyeza kungekudala.',
        'pt' => 'Este resumo está disponível apenas em africâner no momento. As traduções chegarão em breve.',
        'st' => 'Khutsufatso ena e fumaneha ka Seafrikanse feela hajoale. Liphetolelo li tla haufinyane.'
    ],
    'sum_continue_reading' => [
        'af' => 'Gaan voort met lees',
        'en' => 'Continue reading',
        'zu' => 'Qhubeka nokufunda',
        'xh' => 'Qhubeka ufunda',
        'pt' => 'Continuar a leitura',
        'st' => 'Tsoela pele ho bala'
    ],
    'sum_start_reading' => [
        'af' => 'Begin lees',
        'en' => 'Start reading',
        'zu' => 'Qala ukufunda',
        'xh' => 'Qala ukufunda',
        'pt' => 'Começar a ler',
        'st' => 'Qala ho bala'
    ],
    'sum_mark_read' => [
        'af' => 'Merk as gelees',
        'en' => 'Mark as read',
        'zu' => 'Maka njengokufundiwe',
        'xh' => 'Phawula njengefundiweyo',
        'pt' => 'Marcar como lido',
        'st' => 'Tšoaea e le e baliloeng'
    ],
    'sum_marked_read' => [
        'af' => 'Gelees',
        'en' => 'Read',
        'zu' => 'Kufundiwe',
        'xh' => 'Ifundiwe',
        'pt' => 'Lido',
        'st' => 'E baliloe'
    ],
    'sum_next' => [
        'af' => 'Volgende',
        'en' => 'Next',
        'zu' => 'Okulandelayo',
        'xh' => 'Okulandelayo',
        'pt' => 'Próximo',
        'st' => 'E latelang'
    ],
    'sum_prev' => [
        'af' => 'Vorige',
        'en' => 'Previous',
        'zu' => 'Okwedlule',
        'xh' => 'Okwangaphambili',
        'pt' => 'Anterior',
        'st' => 'E fetileng'
    ],
    'sum_read_thread' => [
        'af' => 'Die goue draad',
        'en' => 'The golden thread',
        'zu' => 'Umucu wegolide',
        'xh' => 'Umsonto wegolide',
        'pt' => 'O fio de ouro',
        'st' => 'Khoele ea khauta'
    ],
    'sum_search_placeholder' => [
        'af' => 'Soek in hierdie boek…',
        'en' => 'Search in this book…',
        'zu' => 'Sesha kule ncwadi…',
        'xh' => 'Khangela kule ncwadi…',
        'pt' => 'Pesquisar neste livro…',
        'st' => 'Batla bukeng ena…'
    ],
    'sum_no_results' => [
        'af' => 'Geen resultate gevind nie',
        'en' => 'No results found',
        'zu' => 'Ayikho imiphumela etholiwe',
        'xh' => 'Akukho ziphumo zifunyenweyo',
        'pt' => 'Nenhum resultado encontrado',
        'st' => 'Ha ho liphetho tse fumanoeng'
    ],
    'sum_books_available' => [
        'af' => 'Boeke beskikbaar',
        'en' => 'Books available',
        'zu' => 'Izincwadi ezitholakalayo',
        'xh' => 'Iincwadi ezifumanekayo',
        'pt' => 'Livros disponíveis',
        'st' => 'Libuka tse fumanehang'
    ],

    // ===== Calendar (calendar/) =====
    'month' => [
        'af' => 'Maand',
        'en' => 'Month',
        'zu' => 'Inyanga',
        'xh' => 'Inyanga',
        'pt' => 'Mês',
        'st' => 'Khoeli'
    ],
    'cal_visit' => [
        'af' => 'Besoek',
        'en' => 'Visit',
        'zu' => 'Ukuvakasha',
        'xh' => 'Utyelelo',
        'pt' => 'Visita',
        'st' => 'Ketelo'
    ],
    'cal_birthday' => [
        'af' => 'Verjaarsdag',
        'en' => 'Birthday',
        'zu' => 'Usuku lokuzalwa',
        'xh' => 'Usuku lokuzalwa',
        'pt' => 'Aniversário',
        'st' => 'Letsatsi la tsoalo'
    ],
    'cal_f_events' => [
        'af' => 'Gebeure',
        'en' => 'Events',
        'zu' => 'Imicimbi',
        'xh' => 'Imisitho',
        'pt' => 'Eventos',
        'st' => 'Liketsahalo'
    ],
    'cal_f_visits' => [
        'af' => 'Besoeke',
        'en' => 'Visits',
        'zu' => 'Ukuvakasha',
        'xh' => 'Iityelelo',
        'pt' => 'Visitas',
        'st' => 'Maeto'
    ],
    'cal_f_appointments' => [
        'af' => 'Afsprake',
        'en' => 'Appointments',
        'zu' => 'Izikhathi ezibekiwe',
        'xh' => 'Amadinga',
        'pt' => 'Compromissos',
        'st' => 'Likopano'
    ],
    'cal_f_birthdays' => [
        'af' => 'Verjaarsdae',
        'en' => 'Birthdays',
        'zu' => 'Izinsuku zokuzalwa',
        'xh' => 'Imihla yokuzalwa',
        'pt' => 'Aniversários',
        'st' => 'Matsatsi a tsoalo'
    ],
    'cal_show' => [
        'af' => 'Wys',
        'en' => 'Show',
        'zu' => 'Bonisa',
        'xh' => 'Bonisa',
        'pt' => 'Mostrar',
        'st' => 'Bontša'
    ],
    'cal_new' => [
        'af' => 'Nuwe',
        'en' => 'New',
        'zu' => 'Okusha',
        'xh' => 'Entsha',
        'pt' => 'Novo',
        'st' => 'E ncha'
    ],
    'cal_add' => [
        'af' => 'Voeg by',
        'en' => 'Add',
        'zu' => 'Engeza',
        'xh' => 'Yongeza',
        'pt' => 'Adicionar',
        'st' => 'Kenya'
    ],
    'cal_new_event' => [
        'af' => 'Nuwe gebeurtenis',
        'en' => 'New event',
        'zu' => 'Umcimbi omusha',
        'xh' => 'Umsitho omtsha',
        'pt' => 'Novo evento',
        'st' => 'Ketsahalo e ncha'
    ],
    'cal_new_event_hint' => [
        'af' => 'In ’n kamer: almal in die kamer sien dit',
        'en' => 'In a room: everyone in the room sees it',
        'zu' => 'Egumbini: wonke umuntu osegumbini uyawubona',
        'xh' => 'Kwigumbi: wonke umntu osegumbini uyawubona',
        'pt' => 'Numa sala: todos na sala o veem',
        'st' => 'Kamoreng: bohle ba kamoreng ba e bona'
    ],
    'cal_new_visit' => [
        'af' => 'Nuwe besoek',
        'en' => 'New visit',
        'zu' => 'Ukuvakasha okusha',
        'xh' => 'Utyelelo olutsha',
        'pt' => 'Nova visita',
        'st' => 'Ketelo e ncha'
    ],
    'cal_new_visit_hint' => [
        'af' => 'Privaat: net jy (en jou gade as jy dit deel)',
        'en' => 'Private: only you (and your spouse if you share it)',
        'zu' => 'Kuyimfihlo: nguwe kuphela (nomlingani wakho uma wabelana)',
        'xh' => 'Kuyimfihlo: nguwe kuphela (neqabane lakho ukuba wabelana)',
        'pt' => 'Privado: só você (e o seu cônjuge, se compartilhar)',
        'st' => 'Ke lekunutu: ke uena feela (le molekane oa hao ha o arolelana)'
    ],
    'cal_new_diary' => [
        'af' => 'Dagboekinskrywing',
        'en' => 'Diary entry',
        'zu' => 'Okufakwe kudayari',
        'xh' => 'Ingeniso yedayari',
        'pt' => 'Entrada no diário',
        'st' => 'Ngoliso ea bukana'
    ],
    'cal_new_diary_hint' => [
        'af' => 'Maak jou privaat dagboek oop',
        'en' => 'Opens your private diary',
        'zu' => 'Kuvula idayari yakho eyimfihlo',
        'xh' => 'Ivula idayari yakho eyimfihlo',
        'pt' => 'Abre o seu diário privado',
        'st' => 'E bula bukana ea hao ea lekunutu'
    ],
    'cal_edit_event' => [
        'af' => 'Wysig gebeurtenis',
        'en' => 'Edit event',
        'zu' => 'Hlela umcimbi',
        'xh' => 'Hlela umsitho',
        'pt' => 'Editar evento',
        'st' => 'Fetola ketsahalo'
    ],
    'cal_edit_visit' => [
        'af' => 'Wysig besoek',
        'en' => 'Edit visit',
        'zu' => 'Hlela ukuvakasha',
        'xh' => 'Hlela utyelelo',
        'pt' => 'Editar visita',
        'st' => 'Fetola ketelo'
    ],
    'cal_all_day' => [
        'af' => 'Hele dag',
        'en' => 'All day',
        'zu' => 'Usuku lonke',
        'xh' => 'Imini yonke',
        'pt' => 'Dia inteiro',
        'st' => 'Letsatsi lohle'
    ],
    'cal_add_to_phone' => [
        'af' => 'Voeg by my foon se kalender',
        'en' => 'Add to my phone’s calendar',
        'zu' => 'Engeza kukhalenda yefoni yami',
        'xh' => 'Yongeza kwikhalenda yefowuni yam',
        'pt' => 'Adicionar ao calendário do telefone',
        'st' => 'Kenya khalendareng ea founu ea ka'
    ],
    'cal_send_reminder' => [
        'af' => 'Stuur herinnering aan die kamer',
        'en' => 'Send a reminder to the room',
        'zu' => 'Thumela isikhumbuzo egumbini',
        'xh' => 'Thumela isikhumbuzo kwigumbi',
        'pt' => 'Enviar lembrete à sala',
        'st' => 'Romela khopotso kamoreng'
    ],
    'cal_reminder_sent' => [
        'af' => 'Herinnering gestuur',
        'en' => 'Reminder sent',
        'zu' => 'Isikhumbuzo sithunyelwe',
        'xh' => 'Isikhumbuzo sithunyelwe',
        'pt' => 'Lembrete enviado',
        'st' => 'Khopotso e rometsoe'
    ],
    'cal_reminder_recent' => [
        'af' => 'Jy het reeds ’n herinnering vir hierdie gebeurtenis gestuur. Probeer oor ’n uur weer.',
        'en' => 'You already sent a reminder for this event. Try again in an hour.',
        'zu' => 'Usuthumele isikhumbuzo salo mcimbi. Zama futhi ngemva kwehora.',
        'xh' => 'Sele uthumele isikhumbuzo salo msitho. Zama kwakhona emva kweyure.',
        'pt' => 'Você já enviou um lembrete para este evento. Tente novamente daqui a uma hora.',
        'st' => 'U se u rometse khopotso bakeng sa ketsahalo ena. Leka hape kamora hora.'
    ],
    'cal_open_in_gospel' => [
        'af' => 'Open in Gospel Media',
        'en' => 'Open in Gospel Media',
        'zu' => 'Vula ku-Gospel Media',
        'xh' => 'Vula kwi-Gospel Media',
        'pt' => 'Abrir no Gospel Media',
        'st' => 'Bula ho Gospel Media'
    ],
    'cal_open_in_diary' => [
        'af' => 'Open in dagboek',
        'en' => 'Open in diary',
        'zu' => 'Vula kudayari',
        'xh' => 'Vula kwidayari',
        'pt' => 'Abrir no diário',
        'st' => 'Bula bukaneng'
    ],
    'cal_confirm' => [
        'af' => 'Bevestig',
        'en' => 'Confirm',
        'zu' => 'Qinisekisa',
        'xh' => 'Qinisekisa',
        'pt' => 'Confirmar',
        'st' => 'Netefatsa'
    ],
    'cal_cancel_appt' => [
        'af' => 'Kanselleer afspraak',
        'en' => 'Cancel appointment',
        'zu' => 'Khansela isikhathi esibekiwe',
        'xh' => 'Rhoxisa idinga',
        'pt' => 'Cancelar compromisso',
        'st' => 'Hlakola kopano'
    ],
    'cal_cancel_appt_q' => [
        'af' => 'Kanselleer hierdie afspraak?',
        'en' => 'Cancel this appointment?',
        'zu' => 'Khansela lesi sikhathi esibekiwe?',
        'xh' => 'Rhoxisa eli dinga?',
        'pt' => 'Cancelar este compromisso?',
        'st' => 'Hlakola kopano ena?'
    ],
    'cal_cancel_appt_text' => [
        'af' => 'Die ander persoon kry ’n kennisgewing.',
        'en' => 'The other person will be notified.',
        'zu' => 'Omunye umuntu uzokwaziswa.',
        'xh' => 'Omnye umntu uya kwaziswa.',
        'pt' => 'A outra pessoa será notificada.',
        'st' => 'Motho e mong o tla tsebisoa.'
    ],
    'cal_appt_confirmed' => [
        'af' => 'Afspraak bevestig',
        'en' => 'Appointment confirmed',
        'zu' => 'Isikhathi esibekiwe siqinisekisiwe',
        'xh' => 'Idinga liqinisekisiwe',
        'pt' => 'Compromisso confirmado',
        'st' => 'Kopano e netefalitsoe'
    ],
    'cal_appt_cancelled' => [
        'af' => 'Afspraak gekanselleer',
        'en' => 'Appointment cancelled',
        'zu' => 'Isikhathi esibekiwe sikhanseliwe',
        'xh' => 'Idinga lirhoxisiwe',
        'pt' => 'Compromisso cancelado',
        'st' => 'Kopano e hlakotsoe'
    ],
    'cal_status_pending' => [
        'af' => 'wag op bevestiging',
        'en' => 'awaiting confirmation',
        'zu' => 'kulindwe ukuqinisekiswa',
        'xh' => 'kulindwe ukuqinisekiswa',
        'pt' => 'aguarda confirmação',
        'st' => 'e emetse netefatso'
    ],
    'cal_status_confirmed' => [
        'af' => 'bevestig',
        'en' => 'confirmed',
        'zu' => 'kuqinisekisiwe',
        'xh' => 'kuqinisekisiwe',
        'pt' => 'confirmado',
        'st' => 'e netefalitsoe'
    ],
    'cal_appt_with' => [
        'af' => 'Afspraak met {name}',
        'en' => 'Appointment with {name}',
        'zu' => 'Isikhathi esibekiwe no-{name}',
        'xh' => 'Idinga no-{name}',
        'pt' => 'Compromisso com {name}',
        'st' => 'Kopano le {name}'
    ],
    'cal_birthday_of' => [
        'af' => '{name} verjaar',
        'en' => '{name}’s birthday',
        'zu' => 'Usuku lokuzalwa luka-{name}',
        'xh' => 'Usuku lokuzalwa luka-{name}',
        'pt' => 'Aniversário de {name}',
        'st' => 'Letsatsi la tsoalo la {name}'
    ],
    'cal_your_birthday' => [
        'af' => 'Jou verjaarsdag',
        'en' => 'Your birthday',
        'zu' => 'Usuku lwakho lokuzalwa',
        'xh' => 'Usuku lwakho lokuzalwa',
        'pt' => 'O seu aniversário',
        'st' => 'Letsatsi la hao la tsoalo'
    ],
    'cal_shared_by_spouse' => [
        'af' => 'Gedeel deur jou gade',
        'en' => 'Shared by your spouse',
        'zu' => 'Kwabelwe ngumlingani wakho',
        'xh' => 'Kwabelwe liqabane lakho',
        'pt' => 'Compartilhado pelo seu cônjuge',
        'st' => 'E arolelanoe ke molekane oa hao'
    ],
    'cal_shared_with' => [
        'af' => 'Gedeel met {name}',
        'en' => 'Shared with {name}',
        'zu' => 'Kwabelwana no-{name}',
        'xh' => 'Kwabelwana no-{name}',
        'pt' => 'Compartilhado com {name}',
        'st' => 'E arolelanoe le {name}'
    ],
    'cal_read_only' => [
        'af' => 'Net lees',
        'en' => 'Read only',
        'zu' => 'Ukufunda kuphela',
        'xh' => 'Ukufunda kuphela',
        'pt' => 'Somente leitura',
        'st' => 'Ho bala feela'
    ],
    'cal_nothing_day' => [
        'af' => 'Niks op hierdie dag nie',
        'en' => 'Nothing on this day',
        'zu' => 'Akukho lutho ngalolu suku',
        'xh' => 'Akukho nto ngalo mhla',
        'pt' => 'Nada neste dia',
        'st' => 'Ha ho letho ka letsatsi lena'
    ],
    'cal_nothing_period' => [
        'af' => 'Niks in hierdie tydperk nie',
        'en' => 'Nothing in this period',
        'zu' => 'Akukho lutho kulesi sikhathi',
        'xh' => 'Akukho nto kweli xesha',
        'pt' => 'Nada neste período',
        'st' => 'Ha ho letho nakong ena'
    ],
    'cal_empty_hint' => [
        'af' => 'Tik op “Nuwe” om iets by te voeg.',
        'en' => 'Tap “New” to add something.',
        'zu' => 'Thepha “Okusha” ukuze wengeze okuthile.',
        'xh' => 'Cofa “Entsha” ukongeza into.',
        'pt' => 'Toque em “Novo” para adicionar algo.',
        'st' => 'Tobetsa “E ncha” ho kenya ho hong.'
    ],
    'cal_load_failed' => [
        'af' => 'Kon nie die kalender laai nie.',
        'en' => 'Could not load the calendar.',
        'zu' => 'Ayikwazanga ukulayisha ikhalenda.',
        'xh' => 'Ayikwazanga ukulayisha ikhalenda.',
        'pt' => 'Não foi possível carregar o calendário.',
        'st' => 'Ha ea khona ho kenya khalendara.'
    ],
    'cal_retry' => [
        'af' => 'Probeer weer',
        'en' => 'Try again',
        'zu' => 'Zama futhi',
        'xh' => 'Zama kwakhona',
        'pt' => 'Tentar novamente',
        'st' => 'Leka hape'
    ],
    'cal_delete_q' => [
        'af' => 'Vee hierdie besoek uit?',
        'en' => 'Delete this visit?',
        'zu' => 'Susa lokhu kuvakasha?',
        'xh' => 'Cima olu tyelelo?',
        'pt' => 'Excluir esta visita?',
        'st' => 'Hlakola ketelo ena?'
    ],
    'cal_delete_event_q' => [
        'af' => 'Vee hierdie gebeurtenis uit?',
        'en' => 'Delete this event?',
        'zu' => 'Susa lo mcimbi?',
        'xh' => 'Cima lo msitho?',
        'pt' => 'Excluir este evento?',
        'st' => 'Hlakola ketsahalo ena?'
    ],
    'cal_delete_event_text' => [
        'af' => 'Dit verdwyn ook uit Gospel Media, met sy kommentaar.',
        'en' => 'It also disappears from Gospel Media, with its comments.',
        'zu' => 'Kuzonyamalala naku-Gospel Media, kanye nemibono yakhona.',
        'xh' => 'Iya kunyamalala nakwi-Gospel Media, kunye nezimvo zayo.',
        'pt' => 'Também desaparece do Gospel Media, com os comentários.',
        'st' => 'E tla nyamela le ho Gospel Media, hammoho le maikutlo a eona.'
    ],
    'cal_cannot_undo' => [
        'af' => 'Dit kan nie ontdoen word nie.',
        'en' => 'This cannot be undone.',
        'zu' => 'Lokhu akunakuhlehliswa.',
        'xh' => 'Oku akunakubuyiswa.',
        'pt' => 'Isto não pode ser desfeito.',
        'st' => 'Sena se ke ke sa khutlisoa.'
    ],
    'cal_err_title' => [
        'af' => 'Gee asseblief ’n titel.',
        'en' => 'Please enter a title.',
        'zu' => 'Sicela ufake isihloko.',
        'xh' => 'Nceda ufake isihloko.',
        'pt' => 'Por favor, insira um título.',
        'st' => 'Ka kopo kenya sehlooho.'
    ],
    'cal_err_date' => [
        'af' => 'Kies ’n geldige datum.',
        'en' => 'Choose a valid date.',
        'zu' => 'Khetha usuku olufanele.',
        'xh' => 'Khetha umhla ofanelekileyo.',
        'pt' => 'Escolha uma data válida.',
        'st' => 'Khetha letsatsi le nepahetseng.'
    ],
    'cal_err_time' => [
        'af' => 'Kies ’n geldige tyd, of merk “Hele dag”.',
        'en' => 'Choose a valid time, or tick “All day”.',
        'zu' => 'Khetha isikhathi esifanele, noma umake “Usuku lonke”.',
        'xh' => 'Khetha ixesha elifanelekileyo, okanye uphawule “Imini yonke”.',
        'pt' => 'Escolha uma hora válida ou marque “Dia inteiro”.',
        'st' => 'Khetha nako e nepahetseng, kapa o tšoaee “Letsatsi lohle”.'
    ],
    'cal_err_time_event' => [
        'af' => 'Gebeure in ’n kamer het ’n tyd nodig.',
        'en' => 'Room events need a time.',
        'zu' => 'Imicimbi esegumbini idinga isikhathi.',
        'xh' => 'Imisitho esegumbini ifuna ixesha.',
        'pt' => 'Eventos numa sala precisam de uma hora.',
        'st' => 'Liketsahalo tsa kamore li hloka nako.'
    ],
    'cal_err_room' => [
        'af' => 'Kies ’n kamer.',
        'en' => 'Choose a room.',
        'zu' => 'Khetha igumbi.',
        'xh' => 'Khetha igumbi.',
        'pt' => 'Escolha uma sala.',
        'st' => 'Khetha kamore.'
    ],
    'cal_err_person' => [
        'af' => 'Hierdie persoon kan nie gekies word nie.',
        'en' => 'This person can’t be chosen.',
        'zu' => 'Lo muntu akakwazi ukukhethwa.',
        'xh' => 'Lo mntu akanakukhethwa.',
        'pt' => 'Esta pessoa não pode ser escolhida.',
        'st' => 'Motho enoa a ke ke a khethoa.'
    ],
    'cal_err_forbidden' => [
        'af' => 'Jy het nie regte hiervoor nie.',
        'en' => 'You don’t have permission for this.',
        'zu' => 'Awunayo imvume yalokhu.',
        'xh' => 'Akunayo imvume yoku.',
        'pt' => 'Você não tem permissão para isso.',
        'st' => 'Ha u na tumello ea sena.'
    ],
    'cal_err_session' => [
        'af' => 'Jou sessie het verval. Meld asseblief weer aan.',
        'en' => 'Your session has expired. Please sign in again.',
        'zu' => 'Iseshini yakho iphelelwe yisikhathi. Sicela ungene futhi.',
        'xh' => 'Iseshoni yakho iphelelwe lixesha. Nceda ungene kwakhona.',
        'pt' => 'A sua sessão expirou. Entre novamente.',
        'st' => 'Seshene ea hao e felile. Ka kopo kena hape.'
    ],
    'cal_err_generic' => [
        'af' => 'Iets het skeefgeloop. Probeer asseblief weer.',
        'en' => 'Something went wrong. Please try again.',
        'zu' => 'Kukhona okungahambanga kahle. Sicela uzame futhi.',
        'xh' => 'Kukho into engahambanga kakuhle. Nceda uzame kwakhona.',
        'pt' => 'Algo deu errado. Tente novamente.',
        'st' => 'Ho na le se sa tsamaeang hantle. Ka kopo leka hape.'
    ],
    'cal_err_rate' => [
        'af' => 'Te veel versoeke. Wag asseblief ’n rukkie.',
        'en' => 'Too many requests. Please wait a moment.',
        'zu' => 'Izicelo eziningi kakhulu. Sicela ulinde kancane.',
        'xh' => 'Izicelo ezininzi kakhulu. Nceda ulinde kancinci.',
        'pt' => 'Pedidos demais. Aguarde um momento.',
        'st' => 'Likopo tse ngata haholo. Ka kopo ema hanyane.'
    ],
    'cal_err_not_found' => [
        'af' => 'Hierdie item bestaan nie meer nie.',
        'en' => 'This item no longer exists.',
        'zu' => 'Lento ayisekho.',
        'xh' => 'Le nto ayisekho.',
        'pt' => 'Este item já não existe.',
        'st' => 'Ntho ena ha e sa le eo.'
    ],
    'cal_err_offline' => [
        'af' => 'Geen verbinding nie. Kyk jou internet en probeer weer.',
        'en' => 'No connection. Check your internet and try again.',
        'zu' => 'Akukho xhumano. Hlola i-inthanethi yakho bese uzama futhi.',
        'xh' => 'Akukho qhagamshelwano. Jonga i-intanethi yakho uze uzame kwakhona.',
        'pt' => 'Sem conexão. Verifique a internet e tente novamente.',
        'st' => 'Ha ho khokahano. Hlahloba inthanete ea hao ’me u leke hape.'
    ],
    'cal_err_past' => [
        'af' => 'Hierdie gebeurtenis is reeds verby.',
        'en' => 'This event has already passed.',
        'zu' => 'Lo mcimbi usudlulile.',
        'xh' => 'Lo msitho sele udlulile.',
        'pt' => 'Este evento já passou.',
        'st' => 'Ketsahalo ena e se e fetile.'
    ],
    'cal_err_already' => [
        'af' => 'Dit is reeds gedoen.',
        'en' => 'This has already been done.',
        'zu' => 'Lokhu sekwenziwe kakade.',
        'xh' => 'Oku sele kwenziwe.',
        'pt' => 'Isso já foi feito.',
        'st' => 'Sena se se se entsoe.'
    ],
    'cal_err_no_spouse' => [
        'af' => 'Jy is nie aan ’n gade gekoppel nie.',
        'en' => 'You are not linked to a spouse.',
        'zu' => 'Awuxhunywanga nomlingani.',
        'xh' => 'Akunxulumananga neqabane.',
        'pt' => 'Você não está vinculado a um cônjuge.',
        'st' => 'Ha u hokahane le molekane.'
    ],
    'cal_items' => [
        'af' => '{n} items',
        'en' => '{n} items',
        'zu' => 'izinto ezingu-{n}',
        'xh' => 'izinto ezi-{n}',
        'pt' => '{n} itens',
        'st' => 'lintho tse {n}'
    ],
    'cal_one_item' => [
        'af' => '1 item',
        'en' => '1 item',
        'zu' => 'into engu-1',
        'xh' => 'into e-1',
        'pt' => '1 item',
        'st' => 'ntho e le 1'
    ],
    'cal_more' => [
        'af' => '+{n} meer',
        'en' => '+{n} more',
        'zu' => '+{n} ngaphezulu',
        'xh' => '+{n} ngaphezulu',
        'pt' => '+{n} mais',
        'st' => '+{n} tse ling'
    ],
    'cal_previous' => [
        'af' => 'Vorige',
        'en' => 'Previous',
        'zu' => 'Okwedlule',
        'xh' => 'Okudlulileyo',
        'pt' => 'Anterior',
        'st' => 'E fetileng'
    ],
    'cal_next' => [
        'af' => 'Volgende',
        'en' => 'Next',
        'zu' => 'Okulandelayo',
        'xh' => 'Okulandelayo',
        'pt' => 'Próximo',
        'st' => 'E latelang'
    ],
    'cal_view' => [
        'af' => 'Aansig',
        'en' => 'View',
        'zu' => 'Ukubuka',
        'xh' => 'Imboniselo',
        'pt' => 'Vista',
        'st' => 'Pono'
    ],
    'cal_exporting' => [
        'af' => 'Berei die lêer voor…',
        'en' => 'Preparing the file…',
        'zu' => 'Ilungiselela ifayela…',
        'xh' => 'Ilungiselela ifayile…',
        'pt' => 'Preparando o arquivo…',
        'st' => 'E lokisetsa faele…'
    ],
    'cal_make_appointment' => [
        'af' => 'Maak ’n afspraak',
        'en' => 'Make an appointment',
        'zu' => 'Yenza isikhathi esibekiwe',
        'xh' => 'Yenza idinga',
        'pt' => 'Marcar compromisso',
        'st' => 'Etsa kopano'
    ],
    'cal_spouse_pending' => [
        'af' => 'Iemand wil jou as gade koppel.',
        'en' => 'Someone wants to link with you as spouse.',
        'zu' => 'Othile ufuna ukuxhumana nawe njengomlingani.',
        'xh' => 'Umntu uthile ufuna ukunxulumana nawe njengeqabane.',
        'pt' => 'Alguém quer se vincular a você como cônjuge.',
        'st' => 'Motho e mong o batla ho hokahana le uena e le molekane.'
    ],
    'cal_spouse_view' => [
        'af' => 'Bekyk',
        'en' => 'View',
        'zu' => 'Buka',
        'xh' => 'Jonga',
        'pt' => 'Ver',
        'st' => 'Sheba'
    ],
    'cal_no_rooms' => [
        'af' => 'Jy kan nie in ’n kamer gebeure plaas nie. Besoeke kan jy altyd byvoeg.',
        'en' => 'You can’t post events in a room. You can always add visits.',
        'zu' => 'Awukwazi ukuthumela imicimbi egumbini. Ungahlala wengeza ukuvakasha.',
        'xh' => 'Akukwazi ukuthumela imisitho kwigumbi. Ungasoloko wongeza iityelelo.',
        'pt' => 'Você não pode publicar eventos numa sala. Pode sempre adicionar visitas.',
        'st' => 'U ke ke ua kenya liketsahalo kamoreng. U ka lula u kenya maeto.'
    ],
    'cal_access_denied' => [
        'af' => 'Jy het nie toegang tot hierdie kalender nie.',
        'en' => 'You don’t have access to this calendar.',
        'zu' => 'Awunakho ukufinyelela kule khalenda.',
        'xh' => 'Akunakho ukufikelela kule khalenda.',
        'pt' => 'Você não tem acesso a este calendário.',
        'st' => 'Ha u na phihlello khalendareng ena.'
    ],
    'cal_back_to_mine' => [
        'af' => 'Na my kalender',
        'en' => 'To my calendar',
        'zu' => 'Kukhalenda yami',
        'xh' => 'Kwikhalenda yam',
        'pt' => 'Para o meu calendário',
        'st' => 'Ho khalendara ea ka'
    ],
    'cal_when' => [
        'af' => 'Wanneer',
        'en' => 'When',
        'zu' => 'Nini',
        'xh' => 'Nini',
        'pt' => 'Quando',
        'st' => 'Neng'
    ],
    'cal_by' => [
        'af' => 'Deur {name}',
        'en' => 'By {name}',
        'zu' => 'Ngu-{name}',
        'xh' => 'Ngu-{name}',
        'pt' => 'Por {name}',
        'st' => 'Ka {name}'
    ],
    'cal_ics_ready' => [
        'af' => 'Kalenderlêer afgelaai',
        'en' => 'Calendar file downloaded',
        'zu' => 'Ifayela lekhalenda lilandiwe',
        'xh' => 'Ifayile yekhalenda ikhutshelwe',
        'pt' => 'Arquivo de calendário baixado',
        'st' => 'Faele ea khalendara e jarollotsoe'
    ],
    'cal_page' => [
        'af' => 'Bladsy',
        'en' => 'Page',
        'zu' => 'Ikhasi',
        'xh' => 'Iphepha',
        'pt' => 'Página',
        'st' => 'Leqephe'
    ],
    'cal_generated' => [
        'af' => 'Gegenereer op',
        'en' => 'Generated on',
        'zu' => 'Kwenziwe ngo',
        'xh' => 'Kwenziwe ngo',
        'pt' => 'Gerado em',
        'st' => 'E entsoe ka'
    ],
    'cal_week_n' => [
        'af' => 'Week {n}',
        'en' => 'Week {n}',
        'zu' => 'Iviki {n}',
        'xh' => 'Iveki {n}',
        'pt' => 'Semana {n}',
        'st' => 'Beke {n}'
    ],
    'cal_saving' => [
        'af' => 'Stoor…',
        'en' => 'Saving…',
        'zu' => 'Iyalondoloza…',
        'xh' => 'Iyagcina…',
        'pt' => 'Salvando…',
        'st' => 'E boloka…'
    ],
    'cal_person' => [
        'af' => 'Persoon',
        'en' => 'Person',
        'zu' => 'Umuntu',
        'xh' => 'Umntu',
        'pt' => 'Pessoa',
        'st' => 'Motho'
    ],
    'cal_from_list' => [
        'af' => 'Uit die lys',
        'en' => 'From the list',
        'zu' => 'Ohlwini',
        'xh' => 'Kuluhlu',
        'pt' => 'Da lista',
        'st' => 'Lethathamong'
    ],
    'cal_title_visit_ph' => [
        'af' => 'bv. Huisbesoek by die Smits',
        'en' => 'e.g. Home visit to the Smiths',
        'zu' => 'isib. Ukuvakasha ekhaya kwa-Smith',
        'xh' => 'umz. Utyelelo ekhaya kwa-Smith',
        'pt' => 'ex.: Visita à família Smith',
        'st' => 'mohl. Ketelo lapeng la ha Smith'
    ],
    'cal_title_event_ph' => [
        'af' => 'bv. Jeugaand in die saal',
        'en' => 'e.g. Youth evening in the hall',
        'zu' => 'isib. Ubusuku bentsha ehholo',
        'xh' => 'umz. Ubusuku bolutsha eholweni',
        'pt' => 'ex.: Noite de jovens no salão',
        'st' => 'mohl. Mantsiboea a bacha holong'
    ],
    'cal_room_event_note' => [
        'af' => 'Verskyn ook in Gospel Media',
        'en' => 'Also appears in Gospel Media',
        'zu' => 'Kuvela naku-Gospel Media',
        'xh' => 'Ikwavela nakwi-Gospel Media',
        'pt' => 'Também aparece no Gospel Media',
        'st' => 'E hlaha le ho Gospel Media'
    ],
    'cal_status' => [
        'af' => 'Status',
        'en' => 'Status',
        'zu' => 'Isimo',
        'xh' => 'Imeko',
        'pt' => 'Status',
        'st' => 'Boemo'
    ],
    'cal_notif_visit_msg' => [
        'af' => '{name} het ’n besoek by jou beplan vir {date}.',
        'en' => '{name} has planned a visit with you on {date}.',
        'zu' => '{name} uhlele ukukuvakashela ngo-{date}.',
        'xh' => '{name} ucwangcise ukukutyelela ngo-{date}.',
        'pt' => '{name} planejou uma visita a você em {date}.',
        'st' => '{name} o reriletse ho u etela ka {date}.'
    ],
    'cal_notif_reminder_msg' => [
        'af' => 'Moenie vergeet nie: {event}, {when}.',
        'en' => 'Don’t forget: {event}, {when}.',
        'zu' => 'Ungakhohlwa: {event}, {when}.',
        'xh' => 'Musa ukulibala: {event}, {when}.',
        'pt' => 'Não se esqueça: {event}, {when}.',
        'st' => 'U se ke ua lebala: {event}, {when}.'
    ],
    'cal_notif_appt_confirmed_msg' => [
        'af' => '{name} het jou afspraak op {date} om {time} bevestig.',
        'en' => '{name} confirmed your appointment on {date} at {time}.',
        'zu' => '{name} uqinisekise isikhathi sakho esibekiwe ngo-{date} ngo-{time}.',
        'xh' => '{name} uqinisekise idinga lakho ngo-{date} ngo-{time}.',
        'pt' => '{name} confirmou o seu compromisso em {date} às {time}.',
        'st' => '{name} o netefalitse kopano ea hao ka {date} ka {time}.'
    ],

    // ===== Diary (diary/) =====
    'dy_private' => [
        'af' => 'Privaat — net jy kan dit lees',
        'en' => 'Private — only you can read it',
        'zu' => 'Okuyimfihlo — nguwe kuphela ongakufunda',
        'xh' => 'Iyimfihlo — nguwe kuphela onokuyifunda',
        'pt' => 'Privado — só você pode ler',
        'st' => 'Ke lekunutu — ke uena feela ea ka e balang'
    ],
    'dy_write_today' => [
        'af' => 'Skryf vandag se bladsy',
        'en' => 'Write today’s page',
        'zu' => 'Bhala ikhasi lanamuhla',
        'xh' => 'Bhala iphepha lanamhlanje',
        'pt' => 'Escrever a página de hoje',
        'st' => 'Ngola leqephe la kajeno'
    ],
    'dy_search_label' => [
        'af' => 'Soek in jou dagboek',
        'en' => 'Search your diary',
        'zu' => 'Sesha kudayari yakho',
        'xh' => 'Khangela kwidayari yakho',
        'pt' => 'Pesquisar no seu diário',
        'st' => 'Batla bukeng ea hao ea letsatsi'
    ],
    'dy_clear_search' => [
        'af' => 'Maak soektog skoon',
        'en' => 'Clear search',
        'zu' => 'Sula usesho',
        'xh' => 'Cima ukhangelo',
        'pt' => 'Limpar pesquisa',
        'st' => 'Hlakola patlo'
    ],
    'dy_sort_label' => [
        'af' => 'Volgorde',
        'en' => 'Order',
        'zu' => 'Uhlelo',
        'xh' => 'Ulandelelwano',
        'pt' => 'Ordem',
        'st' => 'Tatellano'
    ],
    'dy_export' => [
        'af' => 'Laai af',
        'en' => 'Download',
        'zu' => 'Landa',
        'xh' => 'Khuphela',
        'pt' => 'Baixar',
        'st' => 'Jarolla'
    ],
    'dy_period' => [
        'af' => 'Tydperk',
        'en' => 'Period',
        'zu' => 'Isikhathi',
        'xh' => 'Ixesha',
        'pt' => 'Período',
        'st' => 'Nako'
    ],
    'dy_on_this_day' => [
        'af' => 'Op hierdie dag',
        'en' => 'On this day',
        'zu' => 'Ngalolu suku',
        'xh' => 'Ngalo mhla',
        'pt' => 'Neste dia',
        'st' => 'Ka letsatsi lena'
    ],
    'dy_load_more' => [
        'af' => 'Wys meer',
        'en' => 'Show more',
        'zu' => 'Bonisa okwengeziwe',
        'xh' => 'Bonisa ezingakumbi',
        'pt' => 'Mostrar mais',
        'st' => 'Bontša tse ling'
    ],
    'dy_yesterday' => [
        'af' => 'Gister',
        'en' => 'Yesterday',
        'zu' => 'Izolo',
        'xh' => 'Izolo',
        'pt' => 'Ontem',
        'st' => 'Maobane'
    ],
    'dy_entries_n' => [
        'af' => '{n} inskrywings',
        'en' => '{n} entries',
        'zu' => 'okufakiwe okungu-{n}',
        'xh' => 'iingeniso ezi-{n}',
        'pt' => '{n} entradas',
        'st' => 'lintlha tse {n}'
    ],
    'dy_entry_one' => [
        'af' => '1 inskrywing',
        'en' => '1 entry',
        'zu' => 'okufakiwe oku-1',
        'xh' => 'ingeniso e-1',
        'pt' => '1 entrada',
        'st' => 'ntlha e le 1'
    ],
    'dy_found_n' => [
        'af' => '{n} gevind',
        'en' => '{n} found',
        'zu' => 'kutholwe okungu-{n}',
        'xh' => 'kufunyenwe ezi-{n}',
        'pt' => '{n} encontradas',
        'st' => 'ho fumanoe tse {n}'
    ],
    'dy_found_one' => [
        'af' => '1 gevind',
        'en' => '1 found',
        'zu' => 'kutholwe oku-1',
        'xh' => 'kufunyenwe e-1',
        'pt' => '1 encontrada',
        'st' => 'ho fumanoe e le 1'
    ],
    'dy_words_n' => [
        'af' => '{n} woorde',
        'en' => '{n} words',
        'zu' => 'amagama angu-{n}',
        'xh' => 'amagama angama-{n}',
        'pt' => '{n} palavras',
        'st' => 'mantsoe a {n}'
    ],
    'dy_word_one' => [
        'af' => '1 woord',
        'en' => '1 word',
        'zu' => 'igama elilodwa',
        'xh' => 'igama elinye',
        'pt' => '1 palavra',
        'st' => 'lentsoe le le leng'
    ],
    'dy_no_title' => [
        'af' => 'Sonder titel',
        'en' => 'Untitled',
        'zu' => 'Akunasihloko',
        'xh' => 'Akukho sihloko',
        'pt' => 'Sem título',
        'st' => 'Ha ho sehlooho'
    ],
    'dy_empty_title' => [
        'af' => 'Jou dagboek is nog leeg',
        'en' => 'Your diary is still empty',
        'zu' => 'Idayari yakho isengenalutho',
        'xh' => 'Idayari yakho isengenanto',
        'pt' => 'Seu diário ainda está vazio',
        'st' => 'Buka ea hao ea letsatsi e ntse e se na letho'
    ],
    'dy_empty_hint' => [
        'af' => 'Skryf jou eerste bladsy — net jy kan dit lees.',
        'en' => 'Write your first page — only you can read it.',
        'zu' => 'Bhala ikhasi lakho lokuqala — nguwe kuphela ongalifunda.',
        'xh' => 'Bhala iphepha lakho lokuqala — nguwe kuphela onokulifunda.',
        'pt' => 'Escreva sua primeira página — só você pode lê-la.',
        'st' => 'Ngola leqephe la hao la pele — ke uena feela ea ka le balang.'
    ],
    'dy_write_first' => [
        'af' => 'Skryf jou eerste bladsy',
        'en' => 'Write your first page',
        'zu' => 'Bhala ikhasi lakho lokuqala',
        'xh' => 'Bhala iphepha lakho lokuqala',
        'pt' => 'Escrever sua primeira página',
        'st' => 'Ngola leqephe la hao la pele'
    ],
    'dy_none_found' => [
        'af' => 'Niks gevind nie',
        'en' => 'Nothing found',
        'zu' => 'Akutholakalanga lutho',
        'xh' => 'Akufunyenwanga nto',
        'pt' => 'Nada encontrado',
        'st' => 'Ha ho letho le fumanoeng'
    ],
    'dy_none_found_hint' => [
        'af' => 'Probeer ander woorde of ’n ander tydperk.',
        'en' => 'Try other words or another period.',
        'zu' => 'Zama amanye amagama noma esinye isikhathi.',
        'xh' => 'Zama amanye amagama okanye elinye ixesha.',
        'pt' => 'Tente outras palavras ou outro período.',
        'st' => 'Leka mantsoe a mang kapa nako e ’ngoe.'
    ],
    'dy_show_all' => [
        'af' => 'Wys alles',
        'en' => 'Show everything',
        'zu' => 'Bonisa konke',
        'xh' => 'Bonisa konke',
        'pt' => 'Mostrar tudo',
        'st' => 'Bontša tsohle'
    ],
    'dy_load_failed' => [
        'af' => 'Kon nie jou dagboek laai nie.',
        'en' => 'Could not load your diary.',
        'zu' => 'Ayikwazanga ukulayisha idayari yakho.',
        'xh' => 'Ayikwazanga ukulayisha idayari yakho.',
        'pt' => 'Não foi possível carregar seu diário.',
        'st' => 'Ha ea khona ho kenya buka ea hao ea letsatsi.'
    ],
    'dy_retry' => [
        'af' => 'Probeer weer',
        'en' => 'Try again',
        'zu' => 'Zama futhi',
        'xh' => 'Zama kwakhona',
        'pt' => 'Tentar novamente',
        'st' => 'Leka hape'
    ],
    'dy_years_ago' => [
        'af' => '{n} jaar gelede',
        'en' => '{n} years ago',
        'zu' => 'eminyakeni engu-{n} edlule',
        'xh' => 'kwiminyaka eyi-{n} edlulileyo',
        'pt' => 'há {n} anos',
        'st' => 'lilemong tse {n} tse fetileng'
    ],
    'dy_year_ago' => [
        'af' => '’n Jaar gelede',
        'en' => 'A year ago',
        'zu' => 'Onyakeni odlule',
        'xh' => 'Kunyaka ophelileyo',
        'pt' => 'Há um ano',
        'st' => 'Selemong se fetileng'
    ],
    'dy_tag_filter' => [
        'af' => '#{tag}',
        'en' => '#{tag}',
        'zu' => '#{tag}',
        'xh' => '#{tag}',
        'pt' => '#{tag}',
        'st' => '#{tag}'
    ],
    'dy_remove_filter' => [
        'af' => 'Verwyder filter',
        'en' => 'Remove filter',
        'zu' => 'Susa isihlungi',
        'xh' => 'Susa isihluzi',
        'pt' => 'Remover filtro',
        'st' => 'Tlosa sefa'
    ],
    'dy_reading' => [
        'af' => 'Lees inskrywing',
        'en' => 'Reading entry',
        'zu' => 'Ukufunda okufakiwe',
        'xh' => 'Ukufunda ingeniso',
        'pt' => 'Lendo a entrada',
        'st' => 'Ho bala ntlha'
    ],
    'dy_written' => [
        'af' => 'Geskryf {date}',
        'en' => 'Written {date}',
        'zu' => 'Kubhalwe ngo-{date}',
        'xh' => 'Ibhalwe ngo-{date}',
        'pt' => 'Escrito em {date}',
        'st' => 'E ngotsoe ka {date}'
    ],
    'dy_edited' => [
        'af' => 'Gewysig {date}',
        'en' => 'Edited {date}',
        'zu' => 'Kuhlelwe ngo-{date}',
        'xh' => 'Ihlelwe ngo-{date}',
        'pt' => 'Editado em {date}',
        'st' => 'E fetotsoe ka {date}'
    ],
    'dy_open_calendar' => [
        'af' => 'Wys in kalender',
        'en' => 'Show in calendar',
        'zu' => 'Bonisa ekhalendeni',
        'xh' => 'Bonisa kwikhalenda',
        'pt' => 'Mostrar no calendário',
        'st' => 'Bontša khalendareng'
    ],
    'dy_prev' => [
        'af' => 'Vorige inskrywing',
        'en' => 'Previous entry',
        'zu' => 'Okufakiwe kwangaphambili',
        'xh' => 'Ingeniso yangaphambili',
        'pt' => 'Entrada anterior',
        'st' => 'Ntlha e fetileng'
    ],
    'dy_next' => [
        'af' => 'Volgende inskrywing',
        'en' => 'Next entry',
        'zu' => 'Okufakiwe okulandelayo',
        'xh' => 'Ingeniso elandelayo',
        'pt' => 'Próxima entrada',
        'st' => 'Ntlha e latelang'
    ],
    'dy_deleted' => [
        'af' => 'Inskrywing verwyder',
        'en' => 'Entry deleted',
        'zu' => 'Okufakiwe kususiwe',
        'xh' => 'Ingeniso icinyiwe',
        'pt' => 'Entrada excluída',
        'st' => 'Ntlha e hlakotsoe'
    ],
    'dy_delete_q' => [
        'af' => 'Verwyder hierdie inskrywing?',
        'en' => 'Delete this entry?',
        'zu' => 'Susa lokhu okufakiwe?',
        'xh' => 'Cima le ngeniso?',
        'pt' => 'Excluir esta entrada?',
        'st' => 'Hlakola ntlha ena?'
    ],
    'dy_cannot_undo' => [
        'af' => 'Dit kan nie ontdoen word nie.',
        'en' => 'This cannot be undone.',
        'zu' => 'Lokhu akunakuhlehliswa.',
        'xh' => 'Oku akunakubuyiswa.',
        'pt' => 'Isso não pode ser desfeito.',
        'st' => 'Sena se ke ke sa khutlisoa.'
    ],
    'dy_writing' => [
        'af' => 'Jou teks',
        'en' => 'Your text',
        'zu' => 'Umbhalo wakho',
        'xh' => 'Umbhalo wakho',
        'pt' => 'Seu texto',
        'st' => 'Mongolo oa hao'
    ],
    'dy_edit_entry' => [
        'af' => 'Wysig inskrywing',
        'en' => 'Edit entry',
        'zu' => 'Hlela okufakiwe',
        'xh' => 'Hlela ingeniso',
        'pt' => 'Editar entrada',
        'st' => 'Fetola ntlha'
    ],
    'dy_optional' => [
        'af' => 'opsioneel',
        'en' => 'optional',
        'zu' => 'ongakukhetha',
        'xh' => 'ukuzikhethela',
        'pt' => 'opcional',
        'st' => 'ka boikhethelo'
    ],
    'dy_tag_ph' => [
        'af' => 'Nuwe etiket…',
        'en' => 'New tag…',
        'zu' => 'Ilebula elisha…',
        'xh' => 'Ilebhuli entsha…',
        'pt' => 'Nova etiqueta…',
        'st' => 'Maraka e ncha…'
    ],
    'dy_add_tag' => [
        'af' => 'Voeg etiket by',
        'en' => 'Add tag',
        'zu' => 'Engeza ilebula',
        'xh' => 'Yongeza ilebhuli',
        'pt' => 'Adicionar etiqueta',
        'st' => 'Kenya maraka'
    ],
    'dy_remove_tag' => [
        'af' => 'Verwyder #{tag}',
        'en' => 'Remove #{tag}',
        'zu' => 'Susa #{tag}',
        'xh' => 'Susa #{tag}',
        'pt' => 'Remover #{tag}',
        'st' => 'Tlosa #{tag}'
    ],
    'dy_calendar_note' => [
        'af' => 'Wys op hierdie dag in jou eie kalender — net jy sien dit.',
        'en' => 'Shows on this day in your own calendar — only you can see it.',
        'zu' => 'Kuvela ngalolu suku ekhalendeni lakho — nguwe kuphela okubonayo.',
        'xh' => 'Ivela ngalo mhla kwikhalenda yakho — nguwe kuphela oyibonayo.',
        'pt' => 'Aparece neste dia no seu próprio calendário — só você pode ver.',
        'st' => 'E hlaha ka letsatsi lena khalendareng ea hao — ke uena feela ea e bonang.'
    ],
    'dy_saving' => [
        'af' => 'Stoor…',
        'en' => 'Saving…',
        'zu' => 'Iyalondoloza…',
        'xh' => 'Iyagcina…',
        'pt' => 'Salvando…',
        'st' => 'E boloka…'
    ],
    'dy_saved' => [
        'af' => 'Gestoor',
        'en' => 'Saved',
        'zu' => 'Kulondoloziwe',
        'xh' => 'Kugciniwe',
        'pt' => 'Salvo',
        'st' => 'E bolokiloe'
    ],
    'dy_discard_q' => [
        'af' => 'Gooi jou veranderinge weg?',
        'en' => 'Discard your changes?',
        'zu' => 'Lahla izinguquko zakho?',
        'xh' => 'Lahla utshintsho lwakho?',
        'pt' => 'Descartar suas alterações?',
        'st' => 'Lahla liphetoho tsa hao?'
    ],
    'dy_discard_text' => [
        'af' => 'Wat jy geskryf het, sal nie gestoor word nie.',
        'en' => 'What you wrote will not be saved.',
        'zu' => 'Lokho okubhalile ngeke kulondolozwe.',
        'xh' => 'Oko ukubhalileyo akuyi kugcinwa.',
        'pt' => 'O que você escreveu não será salvo.',
        'st' => 'Seo u se ngotseng se ke ke sa bolokoa.'
    ],
    'dy_discard' => [
        'af' => 'Gooi weg',
        'en' => 'Discard',
        'zu' => 'Lahla',
        'xh' => 'Lahla',
        'pt' => 'Descartar',
        'st' => 'Lahla'
    ],
    'dy_keep_writing' => [
        'af' => 'Hou aan skryf',
        'en' => 'Keep writing',
        'zu' => 'Qhubeka ubhala',
        'xh' => 'Qhubeka ubhala',
        'pt' => 'Continuar escrevendo',
        'st' => 'Tsoela pele u ngola'
    ],
    'dy_draft_restored' => [
        'af' => 'Jou onvoltooide teks is teruggesit.',
        'en' => 'Your unfinished text was brought back.',
        'zu' => 'Umbhalo wakho ongaqediwe ubuyisiwe.',
        'xh' => 'Umbhalo wakho ongagqitywanga ubuyisiwe.',
        'pt' => 'Seu texto inacabado foi recuperado.',
        'st' => 'Mongolo oa hao o sa phethoang o khutlisitsoe.'
    ],
    'dy_draft_discard' => [
        'af' => 'Begin oor',
        'en' => 'Start over',
        'zu' => 'Qala kabusha',
        'xh' => 'Qala kwakhona',
        'pt' => 'Começar de novo',
        'st' => 'Qala bocha'
    ],
    'dy_draft_kept' => [
        'af' => 'Jou teks is op hierdie toestel bewaar.',
        'en' => 'Your text is kept on this device.',
        'zu' => 'Umbhalo wakho ugcinwe kule divayisi.',
        'xh' => 'Umbhalo wakho ugcinwe kwesi sixhobo.',
        'pt' => 'Seu texto ficou guardado neste aparelho.',
        'st' => 'Mongolo oa hao o bolokiloe sesebelisoeng sena.'
    ],
    'dy_undo' => [
        'af' => 'Ontdoen',
        'en' => 'Undo',
        'zu' => 'Hlehlisa',
        'xh' => 'Buyisa',
        'pt' => 'Desfazer',
        'st' => 'Khutlisa'
    ],
    'dy_share_title' => [
        'af' => 'Deel inskrywing',
        'en' => 'Share entry',
        'zu' => 'Yabelana ngokufakiwe',
        'xh' => 'Yabelana ngengeniso',
        'pt' => 'Compartilhar entrada',
        'st' => 'Arolelana ntlha'
    ],
    'dy_share_text' => [
        'af' => 'Deel as teks',
        'en' => 'Share as text',
        'zu' => 'Yabelana njengombhalo',
        'xh' => 'Yabelana njengombhalo',
        'pt' => 'Compartilhar como texto',
        'st' => 'Arolelana joalo ka mongolo'
    ],
    'dy_share_text_hint' => [
        'af' => 'Jy kies self met wie — niks word aanlyn gepubliseer nie.',
        'en' => 'You choose who — nothing is published online.',
        'zu' => 'Uzikhethela ukuthi ubani — akukho okushicilelwa ku-inthanethi.',
        'xh' => 'Uzikhethela ukuba ngubani — akukho nto ipapashwa kwi-intanethi.',
        'pt' => 'Você escolhe com quem — nada é publicado on-line.',
        'st' => 'U ikhethela hore ke mang — ha ho letho le phatlalatsoang inthaneteng.'
    ],
    'dy_copy_text' => [
        'af' => 'Kopieer teks',
        'en' => 'Copy text',
        'zu' => 'Kopisha umbhalo',
        'xh' => 'Kopa umbhalo',
        'pt' => 'Copiar texto',
        'st' => 'Kopitsa mongolo'
    ],
    'dy_copy_failed' => [
        'af' => 'Kon nie kopieer nie',
        'en' => 'Could not copy',
        'zu' => 'Akukwazanga ukukopisha',
        'xh' => 'Ayikwazanga ukukopa',
        'pt' => 'Não foi possível copiar',
        'st' => 'Ha ea khona ho kopitsa'
    ],
    'dy_download_pdf' => [
        'af' => 'Laai af as PDF',
        'en' => 'Download as PDF',
        'zu' => 'Landa njenge-PDF',
        'xh' => 'Khuphela njenge-PDF',
        'pt' => 'Baixar como PDF',
        'st' => 'Jarolla joalo ka PDF'
    ],
    'dy_downloading' => [
        'af' => 'Jou lêer word afgelaai…',
        'en' => 'Your file is downloading…',
        'zu' => 'Ifayela lakho liyalandwa…',
        'xh' => 'Ifayile yakho iyakhutshelwa…',
        'pt' => 'Seu arquivo está sendo baixado…',
        'st' => 'Faele ea hao ea jarolloa…'
    ],
    'dy_export_title' => [
        'af' => 'Laai jou dagboek af',
        'en' => 'Download your diary',
        'zu' => 'Landa idayari yakho',
        'xh' => 'Khuphela idayari yakho',
        'pt' => 'Baixar seu diário',
        'st' => 'Jarolla buka ea hao ea letsatsi'
    ],
    'dy_export_hint' => [
        'af' => 'Die inskrywings wat jy nou sien ({n}).',
        'en' => 'The entries you see now ({n}).',
        'zu' => 'Okufakiwe okubonayo manje ({n}).',
        'xh' => 'Iingeniso ozibonayo ngoku ({n}).',
        'pt' => 'As entradas que você vê agora ({n}).',
        'st' => 'Lintlha tseo u li bonang hona joale ({n}).'
    ],
    'dy_export_pdf' => [
        'af' => 'PDF-dokument',
        'en' => 'PDF document',
        'zu' => 'Idokhumenti ye-PDF',
        'xh' => 'Uxwebhu lwe-PDF',
        'pt' => 'Documento PDF',
        'st' => 'Tokomane ea PDF'
    ],
    'dy_export_pdf_hint' => [
        'af' => 'Om te bewaar of te druk',
        'en' => 'To keep or print',
        'zu' => 'Ukuze uyigcine noma uyiphrinte',
        'xh' => 'Ukuze uyigcine okanye uyiprinte',
        'pt' => 'Para guardar ou imprimir',
        'st' => 'Ho e boloka kapa ho e hatisa'
    ],
    'dy_export_txt' => [
        'af' => 'Teks (.txt)',
        'en' => 'Text (.txt)',
        'zu' => 'Umbhalo (.txt)',
        'xh' => 'Umbhalo (.txt)',
        'pt' => 'Texto (.txt)',
        'st' => 'Mongolo (.txt)'
    ],
    'dy_export_txt_hint' => [
        'af' => '’n Eenvoudige rugsteun wat enige toestel kan oopmaak',
        'en' => 'A simple backup any device can open',
        'zu' => 'Ikhophi elula yokuphepha noma iyiphi idivayisi engayivula',
        'xh' => 'Ikopi elula yogcino nasiphi na isixhobo esinokuyivula',
        'pt' => 'Uma cópia de segurança simples que qualquer aparelho abre',
        'st' => 'Kopi e bonolo ea polokeho eo sesebelisoa sefe kapa sefe se ka e bulang'
    ],
    'dy_ai_title' => [
        'af' => 'AI-voorstel',
        'en' => 'AI suggestion',
        'zu' => 'Isiphakamiso se-AI',
        'xh' => 'Isindululo se-AI',
        'pt' => 'Sugestão da IA',
        'st' => 'Tlhahiso ea AI'
    ],
    'dy_ai_hint' => [
        'af' => 'Niks verander voordat jy kies nie. Jou teks is vir hierdie voorstel na ’n AI-diens (OpenAI) gestuur.',
        'en' => 'Nothing changes until you choose. Your text was sent to an AI service (OpenAI) for this suggestion.',
        'zu' => 'Akukho okushintshayo uze ukhethe. Umbhalo wakho uthunyelwe kusevisi ye-AI (OpenAI) ukuze kutholakale lesi siphakamiso.',
        'xh' => 'Akukho nto itshintshayo ude ukhethe. Umbhalo wakho uthunyelwe kwinkonzo ye-AI (OpenAI) ukufumana esi sindululo.',
        'pt' => 'Nada muda até você escolher. Seu texto foi enviado a um serviço de IA (OpenAI) para esta sugestão.',
        'st' => 'Ha ho letho le fetohang ho fihlela u khetha. Mongolo oa hao o rometsoe tšebeletsong ea AI (OpenAI) bakeng sa tlhahiso ena.'
    ],
    'dy_ai_use' => [
        'af' => 'Gebruik hierdie teks',
        'en' => 'Use this text',
        'zu' => 'Sebenzisa lo mbhalo',
        'xh' => 'Sebenzisa lo mbhalo',
        'pt' => 'Usar este texto',
        'st' => 'Sebelisa mongolo ona'
    ],
    'dy_ai_keep' => [
        'af' => 'Hou my teks',
        'en' => 'Keep my text',
        'zu' => 'Gcina umbhalo wami',
        'xh' => 'Gcina umbhalo wam',
        'pt' => 'Manter meu texto',
        'st' => 'Boloka mongolo oa ka'
    ],
    'dy_ai_working' => [
        'af' => 'AI dink…',
        'en' => 'AI is thinking…',
        'zu' => 'I-AI iyacabanga…',
        'xh' => 'I-AI iyacinga…',
        'pt' => 'A IA está pensando…',
        'st' => 'AI e nahana…'
    ],
    'dy_ai_used' => [
        'af' => 'Voorstel gebruik — jy kan dit nog wysig.',
        'en' => 'Suggestion used — you can still change it.',
        'zu' => 'Isiphakamiso sisetshenzisiwe — usengasishintsha.',
        'xh' => 'Isindululo sisetyenzisiwe — usenokusitshintsha.',
        'pt' => 'Sugestão usada — você ainda pode alterá-la.',
        'st' => 'Tlhahiso e sebelisitsoe — u ntse u ka e fetola.'
    ],
    'dy_ai_unavailable' => [
        'af' => 'AI-hulp is nou nie beskikbaar nie. Probeer later weer.',
        'en' => 'AI help is not available right now. Try again later.',
        'zu' => 'Usizo lwe-AI alutholakali manje. Zama futhi kamuva.',
        'xh' => 'Uncedo lwe-AI alufumaneki ngoku. Zama kwakhona emva kwexesha.',
        'pt' => 'A ajuda da IA não está disponível agora. Tente mais tarde.',
        'st' => 'Thuso ea AI ha e fumanehe hona joale. Leka hape hamorao.'
    ],
    'dy_ai_too_long' => [
        'af' => 'Die teks is te lank vir AI-hulp. Probeer ’n korter deel.',
        'en' => 'The text is too long for AI help. Try a shorter part.',
        'zu' => 'Umbhalo mude kakhulu kusizo lwe-AI. Zama ingxenye emfushane.',
        'xh' => 'Umbhalo mde kakhulu kuncedo lwe-AI. Zama inxalenye emfutshane.',
        'pt' => 'O texto é longo demais para a ajuda da IA. Tente uma parte menor.',
        'st' => 'Mongolo o molelele haholo bakeng sa thuso ea AI. Leka karolo e khutšoanyane.'
    ],
    'dy_err_empty' => [
        'af' => 'Skryf eers iets.',
        'en' => 'Write something first.',
        'zu' => 'Bhala okuthile kuqala.',
        'xh' => 'Bhala into kuqala.',
        'pt' => 'Escreva algo primeiro.',
        'st' => 'Ngola ho hong pele.'
    ],
    'dy_err_date' => [
        'af' => 'Kies ’n geldige datum.',
        'en' => 'Choose a valid date.',
        'zu' => 'Khetha usuku olufanele.',
        'xh' => 'Khetha umhla ofanelekileyo.',
        'pt' => 'Escolha uma data válida.',
        'st' => 'Khetha letsatsi le nepahetseng.'
    ],
    'dy_err_time' => [
        'af' => 'Kies ’n geldige tyd, of laat dit leeg.',
        'en' => 'Choose a valid time, or leave it empty.',
        'zu' => 'Khetha isikhathi esifanele, noma usishiye singenalutho.',
        'xh' => 'Khetha ixesha elifanelekileyo, okanye ulishiye lingenanto.',
        'pt' => 'Escolha uma hora válida ou deixe em branco.',
        'st' => 'Khetha nako e nepahetseng, kapa u e tlohele e se na letho.'
    ],
    'dy_err_too_long' => [
        'af' => 'Die teks is te lank vir een inskrywing.',
        'en' => 'The text is too long for one entry.',
        'zu' => 'Umbhalo mude kakhulu ukuba ube okufakiwe okukodwa.',
        'xh' => 'Umbhalo mde kakhulu kwingeniso enye.',
        'pt' => 'O texto é longo demais para uma entrada.',
        'st' => 'Mongolo o molelele haholo bakeng sa ntlha e le ’ngoe.'
    ],
    'dy_err_session' => [
        'af' => 'Jou sessie het verval. Meld asseblief weer aan.',
        'en' => 'Your session has expired. Please sign in again.',
        'zu' => 'Iseshini yakho iphelelwe yisikhathi. Sicela ungene futhi.',
        'xh' => 'Iseshoni yakho iphelelwe lixesha. Nceda ungene kwakhona.',
        'pt' => 'Sua sessão expirou. Entre novamente.',
        'st' => 'Seshene ea hao e felile. Ka kopo kena hape.'
    ],
    'dy_err_offline' => [
        'af' => 'Geen verbinding nie. Kyk jou internet en probeer weer.',
        'en' => 'No connection. Check your internet and try again.',
        'zu' => 'Akukho xhumano. Hlola i-inthanethi yakho bese uzama futhi.',
        'xh' => 'Akukho qhagamshelwano. Jonga i-intanethi yakho uze uzame kwakhona.',
        'pt' => 'Sem conexão. Verifique a internet e tente novamente.',
        'st' => 'Ha ho khokahano. Hlahloba inthanete ea hao ’me u leke hape.'
    ],
    'dy_err_generic' => [
        'af' => 'Iets het skeefgeloop. Probeer asseblief weer.',
        'en' => 'Something went wrong. Please try again.',
        'zu' => 'Kukhona okungahambanga kahle. Sicela uzame futhi.',
        'xh' => 'Kukho into engahambanga kakuhle. Nceda uzame kwakhona.',
        'pt' => 'Algo deu errado. Tente novamente.',
        'st' => 'Ho na le se sa tsamaeang hantle. Ka kopo leka hape.'
    ],
    'dy_err_rate' => [
        'af' => 'Te veel versoeke. Wag asseblief ’n rukkie.',
        'en' => 'Too many requests. Please wait a moment.',
        'zu' => 'Izicelo eziningi kakhulu. Sicela ulinde kancane.',
        'xh' => 'Izicelo ezininzi kakhulu. Nceda ulinde kancinci.',
        'pt' => 'Pedidos demais. Aguarde um momento.',
        'st' => 'Likopo tse ngata haholo. Ka kopo ema hanyane.'
    ],
    'dy_err_not_found' => [
        'af' => 'Hierdie inskrywing bestaan nie meer nie.',
        'en' => 'This entry no longer exists.',
        'zu' => 'Lokhu okufakiwe akusekho.',
        'xh' => 'Le ngeniso ayisekho.',
        'pt' => 'Esta entrada não existe mais.',
        'st' => 'Ntlha ena ha e sa le eo.'
    ],
    'dy_mood_happy' => [
        'af' => 'Bly',
        'en' => 'Happy',
        'zu' => 'Ngijabule',
        'xh' => 'Ndonwabile',
        'pt' => 'Feliz',
        'st' => 'Ke thabile'
    ],
    'dy_mood_sad' => [
        'af' => 'Hartseer',
        'en' => 'Sad',
        'zu' => 'Ngidabukile',
        'xh' => 'Ndilusizi',
        'pt' => 'Triste',
        'st' => 'Ke hloname'
    ],
    'dy_mood_excited' => [
        'af' => 'Opgewonde',
        'en' => 'Excited',
        'zu' => 'Ngithakasele',
        'xh' => 'Ndichulumancile',
        'pt' => 'Animado',
        'st' => 'Ke chesehile'
    ],
    'dy_mood_calm' => [
        'af' => 'Kalm',
        'en' => 'Calm',
        'zu' => 'Ngizolile',
        'xh' => 'Ndizolile',
        'pt' => 'Calmo',
        'st' => 'Ke khutsitse'
    ],
    'dy_mood_thoughtful' => [
        'af' => 'Peinsend',
        'en' => 'Thoughtful',
        'zu' => 'Ngicabanga',
        'xh' => 'Ndicinga nzulu',
        'pt' => 'Pensativo',
        'st' => 'Ke nahanisisa'
    ],
    'dy_mood_grateful' => [
        'af' => 'Dankbaar',
        'en' => 'Grateful',
        'zu' => 'Ngibonga',
        'xh' => 'Ndinombulelo',
        'pt' => 'Grato',
        'st' => 'Ke leboha'
    ],
    'dy_mood_inspired' => [
        'af' => 'Geïnspireer',
        'en' => 'Inspired',
        'zu' => 'Ngikhuthazekile',
        'xh' => 'Ndikhuthazekile',
        'pt' => 'Inspirado',
        'st' => 'Ke khothalitsoe'
    ],
    'dy_mood_tired' => [
        'af' => 'Moeg',
        'en' => 'Tired',
        'zu' => 'Ngikhathele',
        'xh' => 'Ndidiniwe',
        'pt' => 'Cansado',
        'st' => 'Ke khathetse'
    ],
    'dy_weather_sunny' => [
        'af' => 'Sonnig',
        'en' => 'Sunny',
        'zu' => 'Kunelanga',
        'xh' => 'Kukho ilanga',
        'pt' => 'Ensolarado',
        'st' => 'Ho chabile letsatsi'
    ],
    'dy_weather_cloudy' => [
        'af' => 'Bewolk',
        'en' => 'Cloudy',
        'zu' => 'Kunamafu',
        'xh' => 'Kukho amafu',
        'pt' => 'Nublado',
        'st' => 'Ho na le maru'
    ],
    'dy_weather_rainy' => [
        'af' => 'Reën',
        'en' => 'Rainy',
        'zu' => 'Kuyana',
        'xh' => 'Kuyanetha',
        'pt' => 'Chuvoso',
        'st' => 'Ho a na'
    ],
    'dy_weather_stormy' => [
        'af' => 'Stormweer',
        'en' => 'Stormy',
        'zu' => 'Kunesiphepho',
        'xh' => 'Kukho isaqhwithi',
        'pt' => 'Tempestuoso',
        'st' => 'Ho na le sefefo'
    ],
    'dy_weather_snowy' => [
        'af' => 'Sneeu',
        'en' => 'Snowy',
        'zu' => 'Kuneqhwa',
        'xh' => 'Kukho ikhephu',
        'pt' => 'Com neve',
        'st' => 'Ho na le lehloa'
    ],
    'dy_weather_windy' => [
        'af' => 'Winderig',
        'en' => 'Windy',
        'zu' => 'Kunomoya',
        'xh' => 'Kukho umoya',
        'pt' => 'Ventoso',
        'st' => 'Ho na le moea'
    ],

    // ===== Shared: relative times (prayers, notifications, thoughts) =====
    'time_yesterday' => [
        'af' => 'Gister',
        'en' => 'Yesterday',
        'zu' => 'Izolo',
        'xh' => 'Izolo',
        'pt' => 'Ontem',
        'st' => 'Maobane'
    ],
    'time_minutes_ago' => [
        'af' => '{n} min. gelede',
        'en' => '{n} min ago',
        'zu' => '{n} min. edlule',
        'xh' => '{n} min. edlulileyo',
        'pt' => 'há {n} min',
        'st' => 'metsotso e {n} e fetileng'
    ],
    'time_hours_ago' => [
        'af' => '{n} uur gelede',
        'en' => '{n} h ago',
        'zu' => 'amahora angu-{n} edlule',
        'xh' => 'iiyure ezi-{n} ezidlulileyo',
        'pt' => 'há {n} h',
        'st' => 'lihora tse {n} tse fetileng'
    ],

    // ===== Gedagtes (gedagtes/) =====
    'gd_tagline' => [
        'af' => 'Een gedagte vir elke dag, hier bewaar',
        'en' => 'One thought for every day, kept here',
        'zu' => 'Umcabango owodwa wosuku ngalunye, ugcinwe lapha',
        'xh' => 'Ingcinga enye yosuku ngalunye, igcinwe apha',
        'pt' => 'Um pensamento para cada dia, guardado aqui',
        'st' => 'Monahano o le mong bakeng sa letsatsi ka leng, o bolokiloe mona'
    ],
    'gd_copy' => [
        'af' => 'Kopieer',
        'en' => 'Copy',
        'zu' => 'Kopisha',
        'xh' => 'Khuphela',
        'pt' => 'Copiar',
        'st' => 'Kopitsa'
    ],
    'gd_copy_failed' => [
        'af' => 'Kon nie kopieer nie',
        'en' => 'Could not copy',
        'zu' => 'Akukwazekanga ukukopisha',
        'xh' => 'Akukwazekanga ukukhuphela',
        'pt' => 'Não foi possível copiar',
        'st' => 'Ha ea khona ho kopitsa'
    ],
    'gd_pending' => [
        'af' => 'Vandag se gedagte verskyn om {time}',
        'en' => 'Today\'s thought appears at {time}',
        'zu' => 'Umcabango wanamuhla uzovela ngo-{time}',
        'xh' => 'Ingcinga yanamhlanje iza kuvela ngo-{time}',
        'pt' => 'O pensamento de hoje aparece às {time}',
        'st' => 'Monahano oa kajeno o tla hlaha ka {time}'
    ],
    'gd_none_today' => [
        'af' => 'Daar is nog nie \'n gedagte vir vandag nie',
        'en' => 'There is no thought for today yet',
        'zu' => 'Akukabi khona umcabango wanamuhla',
        'xh' => 'Akukabikho ngcinga yanamhlanje',
        'pt' => 'Ainda não há pensamento para hoje',
        'st' => 'Ha ho e-so be le monahano oa kajeno'
    ],
    'gd_none_today_hint' => [
        'af' => 'Hieronder is die gedagtes van vorige dae.',
        'en' => 'Below are the thoughts of earlier days.',
        'zu' => 'Ngezansi kunemicabango yezinsuku ezedlule.',
        'xh' => 'Ngezantsi kukho iingcinga zeentsuku ezidlulileyo.',
        'pt' => 'Abaixo estão os pensamentos dos dias anteriores.',
        'st' => 'Ka tlase ho na le menahano ea matsatsi a fetileng.'
    ],
    'gd_earlier' => [
        'af' => 'Vorige gedagtes',
        'en' => 'Earlier thoughts',
        'zu' => 'Imicabango edlule',
        'xh' => 'Iingcinga zangaphambili',
        'pt' => 'Pensamentos anteriores',
        'st' => 'Menahano e fetileng'
    ],
    'gd_search_ph' => [
        'af' => 'Soek in die gedagtes',
        'en' => 'Search the thoughts',
        'zu' => 'Sesha emicabangweni',
        'xh' => 'Khangela kwiingcinga',
        'pt' => 'Pesquisar nos pensamentos',
        'st' => 'Batla menahanong'
    ],
    'gd_clear_search' => [
        'af' => 'Maak soektog skoon',
        'en' => 'Clear search',
        'zu' => 'Sula usesho',
        'xh' => 'Cima ukhangelo',
        'pt' => 'Limpar pesquisa',
        'st' => 'Hlakola patlo'
    ],
    'gd_count_n' => [
        'af' => '{n} gedagtes',
        'en' => '{n} thoughts',
        'zu' => 'Imicabango: {n}',
        'xh' => 'Iingcinga: {n}',
        'pt' => '{n} pensamentos',
        'st' => 'Menahano: {n}'
    ],
    'gd_count_one' => [
        'af' => '1 gedagte',
        'en' => '1 thought',
        'zu' => 'Imicabango: 1',
        'xh' => 'Iingcinga: 1',
        'pt' => '1 pensamento',
        'st' => 'Menahano: 1'
    ],
    'gd_found_n' => [
        'af' => '{n} gevind',
        'en' => '{n} found',
        'zu' => 'Okutholakele: {n}',
        'xh' => 'Ezifunyenweyo: {n}',
        'pt' => '{n} encontrados',
        'st' => 'Tse fumanoeng: {n}'
    ],
    'gd_found_one' => [
        'af' => '1 gevind',
        'en' => '1 found',
        'zu' => 'Okutholakele: 1',
        'xh' => 'Ezifunyenweyo: 1',
        'pt' => '1 encontrado',
        'st' => 'Tse fumanoeng: 1'
    ],
    'gd_none_found' => [
        'af' => 'Niks gevind nie',
        'en' => 'Nothing found',
        'zu' => 'Akukho okutholakele',
        'xh' => 'Akukho nto ifunyenweyo',
        'pt' => 'Nada encontrado',
        'st' => 'Ha ho letho le fumanoeng'
    ],
    'gd_none_found_hint' => [
        'af' => 'Probeer ander woorde.',
        'en' => 'Try other words.',
        'zu' => 'Zama amanye amagama.',
        'xh' => 'Zama amanye amagama.',
        'pt' => 'Tente outras palavras.',
        'st' => 'Leka mantsoe a mang.'
    ],
    'gd_show_all' => [
        'af' => 'Wys alles',
        'en' => 'Show all',
        'zu' => 'Bonisa konke',
        'xh' => 'Bonisa konke',
        'pt' => 'Mostrar tudo',
        'st' => 'Bontša tsohle'
    ],
    'gd_empty' => [
        'af' => 'Nog geen vorige gedagtes nie',
        'en' => 'No earlier thoughts yet',
        'zu' => 'Ayikabi khona imicabango edlule',
        'xh' => 'Akukabikho ngcinga zangaphambili',
        'pt' => 'Ainda não há pensamentos anteriores',
        'st' => 'Ha ho e-so be le menahano e fetileng'
    ],
    'gd_empty_hint' => [
        'af' => 'Elke dag se gedagte word hier bewaar.',
        'en' => 'Every day\'s thought is kept here.',
        'zu' => 'Umcabango wosuku ngalunye ugcinwa lapha.',
        'xh' => 'Ingcinga yosuku ngalunye igcinwa apha.',
        'pt' => 'O pensamento de cada dia fica guardado aqui.',
        'st' => 'Monahano oa letsatsi ka leng o bolokoa mona.'
    ],
    'gd_load_failed' => [
        'af' => 'Kon nie die gedagtes laai nie',
        'en' => 'Could not load the thoughts',
        'zu' => 'Akukwazekanga ukulayisha imicabango',
        'xh' => 'Akukwazekanga ukulayisha iingcinga',
        'pt' => 'Não foi possível carregar os pensamentos',
        'st' => 'Ha ea khona ho kenya menahano'
    ],
    'gd_retry' => [
        'af' => 'Probeer weer',
        'en' => 'Try again',
        'zu' => 'Zama futhi',
        'xh' => 'Zama kwakhona',
        'pt' => 'Tentar novamente',
        'st' => 'Leka hape'
    ],
    'gd_load_more' => [
        'af' => 'Wys meer',
        'en' => 'Show more',
        'zu' => 'Bonisa okwengeziwe',
        'xh' => 'Bonisa ezingakumbi',
        'pt' => 'Mostrar mais',
        'st' => 'Bontša tse ling'
    ],
    'gd_share_label' => [
        'af' => 'Deel hierdie gedagte',
        'en' => 'Share this thought',
        'zu' => 'Yabelana ngalo mcabango',
        'xh' => 'Yabelana ngale ngcinga',
        'pt' => 'Partilhar este pensamento',
        'st' => 'Arolelana monahano ona'
    ],

    // ===== Kennisgewings (notifications/) =====
    'nt_unread_n' => [
        'af' => '{n} ongelees',
        'en' => '{n} unread',
        'zu' => 'Okungafundiwe: {n}',
        'xh' => 'Ezingafundwanga: {n}',
        'pt' => '{n} por ler',
        'st' => 'Tse sa balloang: {n}'
    ],
    'nt_unread_one' => [
        'af' => '1 ongelees',
        'en' => '1 unread',
        'zu' => 'Okungafundiwe: 1',
        'xh' => 'Ezingafundwanga: 1',
        'pt' => '1 por ler',
        'st' => 'Tse sa balloang: 1'
    ],
    'nt_all_read' => [
        'af' => 'Alles gelees — jy is op datum',
        'en' => 'All read — you\'re up to date',
        'zu' => 'Konke kufundiwe',
        'xh' => 'Zonke zifundiwe',
        'pt' => 'Tudo lido — está em dia',
        'st' => 'Tsohle li baliloe'
    ],
    'nt_unread_label' => [
        'af' => 'Ongelees',
        'en' => 'Unread',
        'zu' => 'Akufundiwe',
        'xh' => 'Ayifundwanga',
        'pt' => 'Por ler',
        'st' => 'Ha e so baloe'
    ],
    'nt_deleted' => [
        'af' => 'Kennisgewing verwyder',
        'en' => 'Notification deleted',
        'zu' => 'Isaziso sisusiwe',
        'xh' => 'Isaziso sicinyiwe',
        'pt' => 'Notificação apagada',
        'st' => 'Tsebiso e hlakotsoe'
    ],
    'nt_deleted_n' => [
        'af' => '{n} kennisgewings verwyder',
        'en' => '{n} notifications deleted',
        'zu' => 'Izaziso ezisusiwe: {n}',
        'xh' => 'Izaziso ezicinyiweyo: {n}',
        'pt' => '{n} notificações apagadas',
        'st' => 'Litsebiso tse hlakotsoeng: {n}'
    ],
    'nt_undo' => [
        'af' => 'Ontdoen',
        'en' => 'Undo',
        'zu' => 'Hlehlisa',
        'xh' => 'Buyisela',
        'pt' => 'Desfazer',
        'st' => 'Khutlisa'
    ],
    'nt_all_marked' => [
        'af' => 'Alles is as gelees gemerk',
        'en' => 'Everything is marked as read',
        'zu' => 'Konke kuphawulwe njengokufundiwe',
        'xh' => 'Zonke ziphawulwe njengezifundiweyo',
        'pt' => 'Tudo marcado como lido',
        'st' => 'Tsohle li tšoailoe hore li baliloe'
    ],
    'nt_clear_read' => [
        'af' => 'Vee geleeste uit',
        'en' => 'Clear read ones',
        'zu' => 'Sula ezifundiwe',
        'xh' => 'Cima ezifundiweyo',
        'pt' => 'Apagar as lidas',
        'st' => 'Hlakola tse baliloeng'
    ],
    'nt_clear_read_q' => [
        'af' => 'Vee alle geleeste kennisgewings uit?',
        'en' => 'Clear all read notifications?',
        'zu' => 'Sula zonke izaziso ezifundiwe?',
        'xh' => 'Cima zonke izaziso ezifundiweyo?',
        'pt' => 'Apagar todas as notificações lidas?',
        'st' => 'Hlakola litsebiso tsohle tse baliloeng?'
    ],
    'nt_clear_read_text' => [
        'af' => '{n} kennisgewings wat jy reeds gelees het, word verwyder. Ongeleeste bly.',
        'en' => '{n} notifications you have already read will be deleted. Unread ones stay.',
        'zu' => 'Izaziso osuzifundile zizosuswa ({n}). Ezingafundiwe zizosala.',
        'xh' => 'Izaziso osele uzifundile ziya kucinywa ({n}). Ezingafundwanga ziyahlala.',
        'pt' => '{n} notificações que já leu serão apagadas. As não lidas ficam.',
        'st' => 'Litsebiso tseo u seng u li badile li tla hlakoloa ({n}). Tse sa balloang li tla sala.'
    ],
    'nt_cleared_n' => [
        'af' => '{n} verwyder',
        'en' => '{n} deleted',
        'zu' => 'Kususiwe: {n}',
        'xh' => 'Kucinyiwe: {n}',
        'pt' => '{n} apagadas',
        'st' => 'Tse hlakotsoeng: {n}'
    ],
    'nt_nothing_to_clear' => [
        'af' => 'Niks om uit te vee nie',
        'en' => 'Nothing to clear',
        'zu' => 'Akukho okokusula',
        'xh' => 'Akukho nto yokucima',
        'pt' => 'Nada para apagar',
        'st' => 'Ha ho letho la ho hlakola'
    ],
    'nt_empty' => [
        'af' => 'Nog geen kennisgewings nie',
        'en' => 'No notifications yet',
        'zu' => 'Azikabi khona izaziso',
        'xh' => 'Akukabikho zaziso',
        'pt' => 'Ainda não há notificações',
        'st' => 'Ha ho e-so be le litsebiso'
    ],
    'nt_empty_hint' => [
        'af' => 'Wanneer iemand vir jou bid, op jou plasing reageer of jou iets laat weet, sien jy dit hier.',
        'en' => 'When someone prays for you, responds to your post or lets you know something, you\'ll see it here.',
        'zu' => 'Uma othile ekuthandazela, esabela kokuthunyelwe nguwe noma ekwazisa okuthile, uzokubona lapha.',
        'xh' => 'Xa umntu ekuthandazela, esabela kwinto oyithumeleyo okanye ekwazisa into, uya kuyibona apha.',
        'pt' => 'Quando alguém orar por si, reagir à sua publicação ou lhe avisar algo, verá aqui.',
        'st' => 'Ha motho a u rapella, a arabela poso ea hao kapa a u tsebisa ho hong, u tla ho bona mona.'
    ],
    'nt_none_filter' => [
        'af' => 'Niks hier nie',
        'en' => 'Nothing here',
        'zu' => 'Akukho lutho lapha',
        'xh' => 'Akukho nto apha',
        'pt' => 'Nada aqui',
        'st' => 'Ha ho letho mona'
    ],
    'nt_show_all' => [
        'af' => 'Wys alles',
        'en' => 'Show all',
        'zu' => 'Bonisa konke',
        'xh' => 'Bonisa konke',
        'pt' => 'Mostrar tudo',
        'st' => 'Bontša tsohle'
    ],
    'nt_load_failed' => [
        'af' => 'Kon nie jou kennisgewings laai nie',
        'en' => 'Could not load your notifications',
        'zu' => 'Akukwazekanga ukulayisha izaziso zakho',
        'xh' => 'Akukwazekanga ukulayisha izaziso zakho',
        'pt' => 'Não foi possível carregar as suas notificações',
        'st' => 'Ha ea khona ho kenya litsebiso tsa hao'
    ],
    'nt_retry' => [
        'af' => 'Probeer weer',
        'en' => 'Try again',
        'zu' => 'Zama futhi',
        'xh' => 'Zama kwakhona',
        'pt' => 'Tentar novamente',
        'st' => 'Leka hape'
    ],
    'nt_load_more' => [
        'af' => 'Wys ouer kennisgewings',
        'en' => 'Show older notifications',
        'zu' => 'Bonisa izaziso ezindala',
        'xh' => 'Bonisa izaziso ezindala',
        'pt' => 'Mostrar notificações mais antigas',
        'st' => 'Bontša litsebiso tsa khale'
    ],
    'nt_err_session' => [
        'af' => 'Jou sessie het verval. Laai die bladsy weer.',
        'en' => 'Your session has expired. Please reload the page.',
        'zu' => 'Iseshini yakho iphelelwe yisikhathi. Sicela ulayishe ikhasi kabusha.',
        'xh' => 'Iseshoni yakho iphelelwe lixesha. Nceda uphinde ulayishe iphepha.',
        'pt' => 'A sua sessão expirou. Recarregue a página.',
        'st' => 'Seshene ea hao e felile. Ka kopo kenya leqephe hape.'
    ],
    'nt_err_offline' => [
        'af' => 'Geen verbinding nie. Probeer weer.',
        'en' => 'No connection. Please try again.',
        'zu' => 'Akukho ukuxhumana. Sicela uzame futhi.',
        'xh' => 'Akukho qhagamshelo. Nceda uzame kwakhona.',
        'pt' => 'Sem ligação. Tente novamente.',
        'st' => 'Ha ho khokahano. Ka kopo leka hape.'
    ],
    'nt_err_generic' => [
        'af' => 'Iets het skeefgeloop. Probeer weer.',
        'en' => 'Something went wrong. Please try again.',
        'zu' => 'Kukhona okungahambanga kahle. Sicela uzame futhi.',
        'xh' => 'Kukho into engahambanga kakuhle. Nceda uzame kwakhona.',
        'pt' => 'Algo correu mal. Tente novamente.',
        'st' => 'Ho na le se sa tsamaeang hantle. Ka kopo leka hape.'
    ],
    'nt_delete_one' => [
        'af' => 'Verwyder kennisgewing',
        'en' => 'Delete notification',
        'zu' => 'Susa isaziso',
        'xh' => 'Cima isaziso',
        'pt' => 'Apagar notificação',
        'st' => 'Hlakola tsebiso'
    ],
    'nt_group_prayer' => [
        'af' => 'Gebede',
        'en' => 'Prayers',
        'zu' => 'Imithandazo',
        'xh' => 'Imithandazo',
        'pt' => 'Orações',
        'st' => 'Lithapelo'
    ],
    'nt_group_gospel' => [
        'af' => 'Gospel Media',
        'en' => 'Gospel Media',
        'zu' => 'Gospel Media',
        'xh' => 'Gospel Media',
        'pt' => 'Gospel Media',
        'st' => 'Gospel Media'
    ],
    'nt_group_calendar' => [
        'af' => 'Kalender',
        'en' => 'Calendar',
        'zu' => 'Ikhalenda',
        'xh' => 'Ikhalenda',
        'pt' => 'Calendário',
        'st' => 'Almanaka'
    ],
    'nt_group_thought' => [
        'af' => 'Gedagtes',
        'en' => 'Thoughts',
        'zu' => 'Imicabango',
        'xh' => 'Iingcinga',
        'pt' => 'Pensamentos',
        'st' => 'Menahano'
    ],
    'nt_group_other' => [
        'af' => 'Ander',
        'en' => 'Other',
        'zu' => 'Okunye',
        'xh' => 'Ezinye',
        'pt' => 'Outras',
        'st' => 'Tse ling'
    ],

    // ===== Gebede (prayers/) =====
    'pr_eyebrow' => [
        'af' => 'Gebedsmuur',
        'en' => 'Prayer wall',
        'zu' => 'Udonga lomthandazo',
        'xh' => 'Udonga lomthandazo',
        'pt' => 'Mural de oração',
        'st' => 'Lebota la thapelo'
    ],
    'pr_prompt' => [
        'af' => 'Waarvoor kan ons saam bid?',
        'en' => 'What can we pray about together?',
        'zu' => 'Singathandazela ini ndawonye?',
        'xh' => 'Singathandazela ntoni kunye?',
        'pt' => 'Por que podemos orar juntos?',
        'st' => 'Re ka rapella eng hammoho?'
    ],
    'pr_filter_mine' => [
        'af' => 'My gebede',
        'en' => 'My prayers',
        'zu' => 'Imithandazo yami',
        'xh' => 'Imithandazo yam',
        'pt' => 'As minhas orações',
        'st' => 'Lithapelo tsa ka'
    ],
    'pr_pray' => [
        'af' => 'Ek bid saam',
        'en' => 'Pray along',
        'zu' => 'Thandaza nabo',
        'xh' => 'Thandaza nabo',
        'pt' => 'Orar junto',
        'st' => 'Rapela le bona'
    ],
    'pr_praying' => [
        'af' => 'Jy bid saam',
        'en' => 'Praying along',
        'zu' => 'Uthandaza nabo',
        'xh' => 'Uthandaza nabo',
        'pt' => 'A orar junto',
        'st' => 'O rapela le bona'
    ],
    'pr_heart' => [
        'af' => 'Stuur \'n hart',
        'en' => 'Send a heart',
        'zu' => 'Thumela inhliziyo',
        'xh' => 'Thumela intliziyo',
        'pt' => 'Enviar um coração',
        'st' => 'Romela pelo'
    ],
    'pr_comment' => [
        'af' => 'Skryf \'n bemoedigende woord',
        'en' => 'Write an encouraging word',
        'zu' => 'Bhala igama elikhuthazayo',
        'xh' => 'Bhala ilizwi elikhuthazayo',
        'pt' => 'Escrever uma palavra de ânimo',
        'st' => 'Ngola lentsoe le khothatsang'
    ],
    'pr_tally_pray_one' => [
        'af' => '1 bid saam',
        'en' => '1 praying',
        'zu' => 'Abathandazayo: 1',
        'xh' => 'Abathandazayo: 1',
        'pt' => '1 a orar',
        'st' => 'Ba rapelang: 1'
    ],
    'pr_tally_pray_n' => [
        'af' => '{n} bid saam',
        'en' => '{n} praying',
        'zu' => 'Abathandazayo: {n}',
        'xh' => 'Abathandazayo: {n}',
        'pt' => '{n} a orar',
        'st' => 'Ba rapelang: {n}'
    ],
    'pr_tally_heart_one' => [
        'af' => '1 hart',
        'en' => '1 heart',
        'zu' => 'Izinhliziyo: 1',
        'xh' => 'Iintliziyo: 1',
        'pt' => '1 coração',
        'st' => 'Lipelo: 1'
    ],
    'pr_tally_heart_n' => [
        'af' => '{n} harte',
        'en' => '{n} hearts',
        'zu' => 'Izinhliziyo: {n}',
        'xh' => 'Iintliziyo: {n}',
        'pt' => '{n} corações',
        'st' => 'Lipelo: {n}'
    ],
    'pr_tally_comments_one' => [
        'af' => '1 kommentaar',
        'en' => '1 comment',
        'zu' => 'Amazwana: 1',
        'xh' => 'Izimvo: 1',
        'pt' => '1 comentário',
        'st' => 'Maikutlo: 1'
    ],
    'pr_tally_comments_n' => [
        'af' => '{n} kommentare',
        'en' => '{n} comments',
        'zu' => 'Amazwana: {n}',
        'xh' => 'Izimvo: {n}',
        'pt' => '{n} comentários',
        'st' => 'Maikutlo: {n}'
    ],
    'pr_edited' => [
        'af' => 'gewysig',
        'en' => 'edited',
        'zu' => 'kuhleliwe',
        'xh' => 'kuhleliwe',
        'pt' => 'editado',
        'st' => 'e fetotsoe'
    ],
    'pr_options' => [
        'af' => 'Opsies',
        'en' => 'Options',
        'zu' => 'Okukhethwa kukho',
        'xh' => 'Ukhetho',
        'pt' => 'Opções',
        'st' => 'Likhetho'
    ],
    'pr_edit' => [
        'af' => 'Wysig',
        'en' => 'Edit',
        'zu' => 'Hlela',
        'xh' => 'Hlela',
        'pt' => 'Editar',
        'st' => 'Fetola'
    ],
    'pr_delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Apagar',
        'st' => 'Hlakola'
    ],
    'pr_new_title' => [
        'af' => 'Deel \'n gebedsnood',
        'en' => 'Share a prayer need',
        'zu' => 'Yabelana ngesidingo somthandazo',
        'xh' => 'Yabelana ngesidingo somthandazo',
        'pt' => 'Partilhar um pedido de oração',
        'st' => 'Arolelana tlhoko ea thapelo'
    ],
    'pr_edit_title' => [
        'af' => 'Wysig jou gebed',
        'en' => 'Edit your prayer',
        'zu' => 'Hlela umthandazo wakho',
        'xh' => 'Hlela umthandazo wakho',
        'pt' => 'Editar a sua oração',
        'st' => 'Fetola thapelo ea hao'
    ],
    'pr_text_ph' => [
        'af' => 'Waarvoor kan ons saam bid? Ander lede sal saam met jou bid.',
        'en' => 'What can we pray about together? Other members will pray with you.',
        'zu' => 'Singathandazela ini ndawonye? Amanye amalungu azothandaza nawe.',
        'xh' => 'Singathandazela ntoni kunye? Amanye amalungu aya kuthandaza nawe.',
        'pt' => 'Por que podemos orar juntos? Outros membros vão orar consigo.',
        'st' => 'Re ka rapella eng hammoho? Litho tse ling li tla rapela le uena.'
    ],
    'pr_add_photo' => [
        'af' => 'Voeg foto by',
        'en' => 'Add photo',
        'zu' => 'Engeza isithombe',
        'xh' => 'Yongeza ifoto',
        'pt' => 'Adicionar foto',
        'st' => 'Kenya foto'
    ],
    'pr_change_photo' => [
        'af' => 'Ander foto',
        'en' => 'Other photo',
        'zu' => 'Esinye isithombe',
        'xh' => 'Enye ifoto',
        'pt' => 'Outra foto',
        'st' => 'Foto e \'ngoe'
    ],
    'pr_remove_photo' => [
        'af' => 'Verwyder foto',
        'en' => 'Remove photo',
        'zu' => 'Susa isithombe',
        'xh' => 'Susa ifoto',
        'pt' => 'Remover foto',
        'st' => 'Tlosa foto'
    ],
    'pr_share_btn' => [
        'af' => 'Deel',
        'en' => 'Share',
        'zu' => 'Yabelana',
        'xh' => 'Yabelana',
        'pt' => 'Partilhar',
        'st' => 'Arolelana'
    ],
    'pr_save_btn' => [
        'af' => 'Stoor',
        'en' => 'Save',
        'zu' => 'Londoloza',
        'xh' => 'Gcina',
        'pt' => 'Guardar',
        'st' => 'Boloka'
    ],
    'pr_posting' => [
        'af' => 'Deel tans…',
        'en' => 'Sharing…',
        'zu' => 'Iyabelana…',
        'xh' => 'Iyabelana…',
        'pt' => 'A partilhar…',
        'st' => 'E a arolelana…'
    ],
    'pr_saving' => [
        'af' => 'Stoor tans…',
        'en' => 'Saving…',
        'zu' => 'Iyalondoloza…',
        'xh' => 'Iyagcina…',
        'pt' => 'A guardar…',
        'st' => 'E a boloka…'
    ],
    'pr_shared' => [
        'af' => 'Jou gebed is gedeel. Ons bid saam.',
        'en' => 'Your prayer has been shared. We pray with you.',
        'zu' => 'Umthandazo wakho wabelwe. Sithandaza nawe.',
        'xh' => 'Umthandazo wakho wabelwe. Sithandaza nawe.',
        'pt' => 'A sua oração foi partilhada. Oramos consigo.',
        'st' => 'Thapelo ea hao e arolelanoe. Re rapela le uena.'
    ],
    'pr_saved' => [
        'af' => 'Gestoor',
        'en' => 'Saved',
        'zu' => 'Kulondoloziwe',
        'xh' => 'Kugciniwe',
        'pt' => 'Guardado',
        'st' => 'E bolokiloe'
    ],
    'pr_deleted' => [
        'af' => 'Gebed verwyder',
        'en' => 'Prayer deleted',
        'zu' => 'Umthandazo ususiwe',
        'xh' => 'Umthandazo ucinyiwe',
        'pt' => 'Oração apagada',
        'st' => 'Thapelo e hlakotsoe'
    ],
    'pr_delete_q' => [
        'af' => 'Verwyder hierdie gebed?',
        'en' => 'Delete this prayer?',
        'zu' => 'Susa lo mthandazo?',
        'xh' => 'Cima lo mthandazo?',
        'pt' => 'Apagar esta oração?',
        'st' => 'Hlakola thapelo ena?'
    ],
    'pr_delete_text' => [
        'af' => 'Die kommentaar en reaksies word ook verwyder. Dit kan nie ontdoen word nie.',
        'en' => 'Its comments and reactions are deleted too. This cannot be undone.',
        'zu' => 'Amazwana nokusabela nakho kuzosuswa. Lokhu akunakuhlehliswa.',
        'xh' => 'Izimvo nokusabela nazo ziya kucinywa. Oku akunakubuyiselwa.',
        'pt' => 'Os comentários e reações também são apagados. Não pode ser desfeito.',
        'st' => 'Maikutlo le likarabo le tsona li tla hlakoloa. Sena se ke ke sa khutlisoa.'
    ],
    'pr_discard_q' => [
        'af' => 'Gooi weg wat jy geskryf het?',
        'en' => 'Discard what you wrote?',
        'zu' => 'Lahla okubhalile?',
        'xh' => 'Lahla oko ukubhalileyo?',
        'pt' => 'Descartar o que escreveu?',
        'st' => 'Lahla seo u se ngotseng?'
    ],
    'pr_discard' => [
        'af' => 'Gooi weg',
        'en' => 'Discard',
        'zu' => 'Lahla',
        'xh' => 'Lahla',
        'pt' => 'Descartar',
        'st' => 'Lahla'
    ],
    'pr_keep' => [
        'af' => 'Hou aan skryf',
        'en' => 'Keep writing',
        'zu' => 'Qhubeka ubhala',
        'xh' => 'Qhubeka ubhala',
        'pt' => 'Continuar a escrever',
        'st' => 'Tsoela pele ho ngola'
    ],
    'pr_no_comments' => [
        'af' => 'Nog geen kommentaar nie. Skryf die eerste bemoedigende woord.',
        'en' => 'No comments yet. Write the first encouraging word.',
        'zu' => 'Awekho amazwana okwamanje. Bhala igama lokuqala elikhuthazayo.',
        'xh' => 'Akukabikho zimvo. Bhala ilizwi lokuqala elikhuthazayo.',
        'pt' => 'Ainda sem comentários. Escreva a primeira palavra de ânimo.',
        'st' => 'Ha ho e-so be le maikutlo. Ngola lentsoe la pele le khothatsang.'
    ],
    'pr_comment_delete_q' => [
        'af' => 'Verwyder hierdie kommentaar?',
        'en' => 'Delete this comment?',
        'zu' => 'Susa leli zwana?',
        'xh' => 'Cima olu luvo?',
        'pt' => 'Apagar este comentário?',
        'st' => 'Hlakola maikutlo ana?'
    ],
    'pr_comment_deleted' => [
        'af' => 'Kommentaar verwyder',
        'en' => 'Comment deleted',
        'zu' => 'Izwana lisusiwe',
        'xh' => 'Uluvo lucinyiwe',
        'pt' => 'Comentário apagado',
        'st' => 'Maikutlo a hlakotsoe'
    ],
    'pr_comment_edit' => [
        'af' => 'Wysig',
        'en' => 'Edit',
        'zu' => 'Hlela',
        'xh' => 'Hlela',
        'pt' => 'Editar',
        'st' => 'Fetola'
    ],
    'pr_comment_saved' => [
        'af' => 'Kommentaar gestoor',
        'en' => 'Comment saved',
        'zu' => 'Izwana lilondoloziwe',
        'xh' => 'Uluvo lugciniwe',
        'pt' => 'Comentário guardado',
        'st' => 'Maikutlo a bolokiloe'
    ],
    'pr_who_title' => [
        'af' => 'Wie bid saam',
        'en' => 'Who is praying',
        'zu' => 'Ubani othandazayo',
        'xh' => 'Ngubani othandazayo',
        'pt' => 'Quem está a orar',
        'st' => 'Ke mang ea rapelang'
    ],
    'pr_tab_pray' => [
        'af' => 'Bid saam',
        'en' => 'Praying',
        'zu' => 'Abathandazayo',
        'xh' => 'Abathandazayo',
        'pt' => 'A orar',
        'st' => 'Ba rapelang'
    ],
    'pr_tab_heart' => [
        'af' => 'Harte',
        'en' => 'Hearts',
        'zu' => 'Izinhliziyo',
        'xh' => 'Iintliziyo',
        'pt' => 'Corações',
        'st' => 'Lipelo'
    ],
    'pr_who_none' => [
        'af' => 'Nog niemand nie',
        'en' => 'No one yet',
        'zu' => 'Akekho okwamanje',
        'xh' => 'Akukabikho mntu',
        'pt' => 'Ainda ninguém',
        'st' => 'Ha ho e-so be le motho'
    ],
    'pr_you' => [
        'af' => 'jy',
        'en' => 'you',
        'zu' => 'wena',
        'xh' => 'wena',
        'pt' => 'você',
        'st' => 'uena'
    ],
    'pr_empty' => [
        'af' => 'Nog geen gebede nie',
        'en' => 'No prayers yet',
        'zu' => 'Ayikabi khona imithandazo',
        'xh' => 'Akukabikho mithandazo',
        'pt' => 'Ainda não há orações',
        'st' => 'Ha ho e-so be le lithapelo'
    ],
    'pr_empty_hint' => [
        'af' => 'Deel die eerste gebedsnood — ander sal saam met jou bid.',
        'en' => 'Share the first prayer need — others will pray with you.',
        'zu' => 'Yabelana ngesidingo sokuqala somthandazo — abanye bazothandaza nawe.',
        'xh' => 'Yabelana ngesidingo sokuqala somthandazo — abanye baya kuthandaza nawe.',
        'pt' => 'Partilhe o primeiro pedido de oração — outros vão orar consigo.',
        'st' => 'Arolelana tlhoko ea pele ea thapelo — ba bang ba tla rapela le uena.'
    ],
    'pr_empty_mine' => [
        'af' => 'Jy het nog nie \'n gebed gedeel nie',
        'en' => 'You haven\'t shared a prayer yet',
        'zu' => 'Awukabelani ngomthandazo',
        'xh' => 'Akukabelani ngomthandazo',
        'pt' => 'Ainda não partilhou nenhuma oração',
        'st' => 'Ha u e-so arolelane thapelo'
    ],
    'pr_empty_town' => [
        'af' => 'Nog geen gebede uit {town} nie',
        'en' => 'No prayers from {town} yet',
        'zu' => 'Ayikabi khona imithandazo evela e-{town}',
        'xh' => 'Akukabikho mithandazo evela e-{town}',
        'pt' => 'Ainda não há orações de {town}',
        'st' => 'Ha ho e-so be le lithapelo tse tsoang {town}'
    ],
    'pr_load_failed' => [
        'af' => 'Kon nie die gebede laai nie',
        'en' => 'Could not load the prayers',
        'zu' => 'Akukwazekanga ukulayisha imithandazo',
        'xh' => 'Akukwazekanga ukulayisha imithandazo',
        'pt' => 'Não foi possível carregar as orações',
        'st' => 'Ha ea khona ho kenya lithapelo'
    ],
    'pr_retry' => [
        'af' => 'Probeer weer',
        'en' => 'Try again',
        'zu' => 'Zama futhi',
        'xh' => 'Zama kwakhona',
        'pt' => 'Tentar novamente',
        'st' => 'Leka hape'
    ],
    'pr_load_more' => [
        'af' => 'Wys meer gebede',
        'en' => 'Show more prayers',
        'zu' => 'Bonisa eminye imithandazo',
        'xh' => 'Bonisa eminye imithandazo',
        'pt' => 'Mostrar mais orações',
        'st' => 'Bontša lithapelo tse ling'
    ],
    'pr_photo_alt' => [
        'af' => 'Foto by die gebed',
        'en' => 'Photo with the prayer',
        'zu' => 'Isithombe somthandazo',
        'xh' => 'Ifoto yomthandazo',
        'pt' => 'Foto da oração',
        'st' => 'Foto ea thapelo'
    ],
    'pr_chars_left' => [
        'af' => '{n} karakters oor',
        'en' => '{n} characters left',
        'zu' => 'Izinhlamvu ezisele: {n}',
        'xh' => 'Oonobumba abaseleyo: {n}',
        'pt' => 'Restam {n} caracteres',
        'st' => 'Litlhaku tse setseng: {n}'
    ],
    'pr_not_found' => [
        'af' => 'Hierdie gebed is nie meer daar nie',
        'en' => 'This prayer is no longer there',
        'zu' => 'Lo mthandazo awusekho',
        'xh' => 'Lo mthandazo awusekho',
        'pt' => 'Esta oração já não existe',
        'st' => 'Thapelo ena ha e sa le teng'
    ],
    'pr_err_session' => [
        'af' => 'Jou sessie het verval. Laai die bladsy weer.',
        'en' => 'Your session has expired. Please reload the page.',
        'zu' => 'Iseshini yakho iphelelwe yisikhathi. Sicela ulayishe ikhasi kabusha.',
        'xh' => 'Iseshoni yakho iphelelwe lixesha. Nceda uphinde ulayishe iphepha.',
        'pt' => 'A sua sessão expirou. Recarregue a página.',
        'st' => 'Seshene ea hao e felile. Ka kopo kenya leqephe hape.'
    ],
    'pr_err_offline' => [
        'af' => 'Geen verbinding nie. Probeer weer.',
        'en' => 'No connection. Please try again.',
        'zu' => 'Akukho ukuxhumana. Sicela uzame futhi.',
        'xh' => 'Akukho qhagamshelo. Nceda uzame kwakhona.',
        'pt' => 'Sem ligação. Tente novamente.',
        'st' => 'Ha ho khokahano. Ka kopo leka hape.'
    ],
    'pr_err_generic' => [
        'af' => 'Iets het skeefgeloop. Probeer weer.',
        'en' => 'Something went wrong. Please try again.',
        'zu' => 'Kukhona okungahambanga kahle. Sicela uzame futhi.',
        'xh' => 'Kukho into engahambanga kakuhle. Nceda uzame kwakhona.',
        'pt' => 'Algo correu mal. Tente novamente.',
        'st' => 'Ho na le se sa tsamaeang hantle. Ka kopo leka hape.'
    ],
    'pr_err_rate' => [
        'af' => 'Jy het baie gedeel. Probeer oor \'n rukkie weer.',
        'en' => 'You have shared a lot. Please try again in a while.',
        'zu' => 'Wabelane kakhulu. Sicela uzame futhi emva kwesikhashana.',
        'xh' => 'Wabelane kakhulu. Nceda uzame kwakhona emva kwexeshana.',
        'pt' => 'Partilhou muito. Tente novamente daqui a pouco.',
        'st' => 'U arolelane haholo. Ka kopo leka hape ka mor\'a nakoana.'
    ],
    'pr_err_too_long' => [
        'af' => 'Die teks is te lank',
        'en' => 'The text is too long',
        'zu' => 'Umbhalo mude kakhulu',
        'xh' => 'Umbhalo mde kakhulu',
        'pt' => 'O texto é demasiado longo',
        'st' => 'Mongolo o molelele haholo'
    ],
    'pr_err_empty' => [
        'af' => 'Skryf eers iets',
        'en' => 'Write something first',
        'zu' => 'Bhala okuthile kuqala',
        'xh' => 'Bhala into kuqala',
        'pt' => 'Escreva algo primeiro',
        'st' => 'Ngola ho hong pele'
    ],
    'pr_err_photo' => [
        'af' => 'Hierdie foto kon nie gebruik word nie',
        'en' => 'This photo could not be used',
        'zu' => 'Lesi sithombe asikwazanga ukusetshenziswa',
        'xh' => 'Le foto ayikwazanga ukusetyenziswa',
        'pt' => 'Não foi possível usar esta foto',
        'st' => 'Foto ena ha ea khona ho sebelisoa'
    ],
    'pr_err_photo_big' => [
        'af' => 'Die foto is te groot',
        'en' => 'The photo is too large',
        'zu' => 'Isithombe sikhulu kakhulu',
        'xh' => 'Ifoto inkulu kakhulu',
        'pt' => 'A foto é demasiado grande',
        'st' => 'Foto e kholo haholo'
    ],
    'pr_err_forbidden' => [
        'af' => 'Jy mag dit nie doen nie',
        'en' => 'You may not do that',
        'zu' => 'Awuvunyelwe ukwenza lokho',
        'xh' => 'Awuvumelekanga ukwenza oko',
        'pt' => 'Não pode fazer isto',
        'st' => 'Ha ua lumelloa ho etsa joalo'
    ],
    'pr_verse' => [
        'af' => 'Bid sonder ophou.',
        'en' => 'Pray without ceasing.',
        'zu' => 'Khulekani ningapheli.',
        'xh' => 'Thandazani ningaphezi.',
        'pt' => 'Orai sem cessar.',
        'st' => 'Rapelang le sa khaotse.'
    ],
    'pr_verse_ref' => [
        'af' => '1 Tessalonisense 5:17',
        'en' => '1 Thessalonians 5:17',
        'zu' => '1 Thesalonika 5:17',
        'xh' => '1 Tesalonika 5:17',
        'pt' => '1 Tessalonicenses 5:17',
        'st' => '1 Bathesalonika 5:17'
    ],

    // ==================== ADMIN "LIGHT THROUGH GLASS" ====================
    'adm_eyebrow' => [
        'af' => 'Bestuur',
        'en' => 'Manage',
        'zu' => 'Phatha',
        'xh' => 'Lawula',
        'pt' => 'Gerir',
        'st' => 'Laola'
    ],
    'adm_err_method' => [
        'af' => 'Daardie versoek word nie toegelaat nie.',
        'en' => 'That request is not allowed.',
        'zu' => 'Lesi sicelo asivunyelwe.',
        'xh' => 'Esi sicelo asivumelekanga.',
        'pt' => 'Esse pedido não é permitido.',
        'st' => 'Kopo eo ha e a lumelloa.'
    ],
    'adm_err_session' => [
        'af' => 'Jou sessie het verval. Herlaai die bladsy en probeer weer.',
        'en' => 'Your session has expired. Please refresh the page and try again.',
        'zu' => 'Isikhathi sakho sesiphelile. Vuselela ikhasi bese uzama futhi.',
        'xh' => 'Iseshoni yakho iphelelwe lixesha. Hlaziya iphepha uzame kwakhona.',
        'pt' => 'A sua sessão expirou. Atualize a página e tente novamente.',
        'st' => 'Nako ea hau e fedile. Ntlafatsa leqephe \'me o leke hape.'
    ],
    'adm_err_invalid' => [
        'af' => 'Iets in die versoek is nie reg nie.',
        'en' => 'Something in the request is not right.',
        'zu' => 'Kukhona okungalungile esicelweni.',
        'xh' => 'Kukho into engachanekanga kwisicelo.',
        'pt' => 'Algo no pedido não está correto.',
        'st' => 'Ho na le ntho e fosahetseng kopong.'
    ],
    'adm_err_forbidden' => [
        'af' => 'Jy mag dit nie doen nie.',
        'en' => 'You are not allowed to do that.',
        'zu' => 'Awuvunyelwe ukukwenza lokho.',
        'xh' => 'Awuvumelekanga ukwenza oko.',
        'pt' => 'Não tem permissão para fazer isso.',
        'st' => 'Ha o lumelloe ho etsa joalo.'
    ],
    'adm_err_server' => [
        'af' => 'Iets het aan ons kant skeefgeloop. Probeer asseblief weer.',
        'en' => 'Something went wrong on our side. Please try again.',
        'zu' => 'Kukhona okungahambanga kahle ohlangothini lwethu. Sicela uzame futhi.',
        'xh' => 'Kukho into engahambanga kakuhle kwicala lethu. Nceda uzame kwakhona.',
        'pt' => 'Algo correu mal do nosso lado. Tente novamente.',
        'st' => 'Ho na le ntho e fosahetseng ka lehlakoreng la rona. Ka kopo leka hape.'
    ],
    'adm_err_not_found' => [
        'af' => 'Ons kon dit nie vind nie.',
        'en' => 'We could not find that.',
        'zu' => 'Asikutholanga lokho.',
        'xh' => 'Asikufumananga oko.',
        'pt' => 'Não foi possível encontrar.',
        'st' => 'Ha re a ka ra e fumana.'
    ],
    'adm_err_rate' => [
        'af' => 'Te veel pogings. Wag \'n paar minute.',
        'en' => 'Too many tries. Please wait a few minutes.',
        'zu' => 'Imizamo eminingi kakhulu. Sicela ulinde imizuzu embalwa.',
        'xh' => 'Imizamo emininzi kakhulu. Nceda ulinde imizuzu embalwa.',
        'pt' => 'Demasiadas tentativas. Aguarde alguns minutos.',
        'st' => 'Maiteko a mangata haholo. Ka kopo ema metsotso e seng mekae.'
    ],
    'adm_err_done' => [
        'af' => 'Dit is reeds afgehandel.',
        'en' => 'That has already been dealt with.',
        'zu' => 'Lokho sekuvele kwenziwe.',
        'xh' => 'Oko sele kwenziwe.',
        'pt' => 'Isso já foi tratado.',
        'st' => 'Seo se se entsoe.'
    ],
    'adm_err_already_married' => [
        'af' => 'Een van hulle is reeds aan \'n eggenoot gekoppel.',
        'en' => 'One of them is already linked to a spouse.',
        'zu' => 'Omunye wabo usevele uhlanganiswe nomlingani.',
        'xh' => 'Omnye wabo sele edityaniswe nomlingane.',
        'pt' => 'Um deles já está ligado a um cônjuge.',
        'st' => 'E mong oa bona o se a hokahantšoe le molekane.'
    ],
    'adm_err_request_exists' => [
        'af' => 'Daardie versoek bestaan reeds.',
        'en' => 'That request already exists.',
        'zu' => 'Lesi sicelo sesivele sikhona.',
        'xh' => 'Esi sicelo sele sikhona.',
        'pt' => 'Esse pedido já existe.',
        'st' => 'Kopo eo e se e ntse e le teng.'
    ],
    'adm_err_past' => [
        'af' => 'Kies \'n datum en tyd in die toekoms.',
        'en' => 'Pick a date and time in the future.',
        'zu' => 'Khetha usuku nesikhathi esizayo.',
        'xh' => 'Khetha umhla nexesha elizayo.',
        'pt' => 'Escolha uma data e hora no futuro.',
        'st' => 'Khetha letsatsi le nako e tlang.'
    ],
    'adm_user_approved' => [
        'af' => 'Lid goedgekeur.',
        'en' => 'Member approved.',
        'zu' => 'Ilungu livunyiwe.',
        'xh' => 'Ilungu livunyiwe.',
        'pt' => 'Membro aprovado.',
        'st' => 'Setho se lumelletsoe.'
    ],
    'adm_user_rejected' => [
        'af' => 'Lid afgewys.',
        'en' => 'Member rejected.',
        'zu' => 'Ilungu alamukelwanga.',
        'xh' => 'Ilungu alamkelwanga.',
        'pt' => 'Membro rejeitado.',
        'st' => 'Setho ha se a amoheloa.'
    ],
    'adm_confirm_reject' => [
        'af' => 'Is jy seker jy wil hierdie lid afwys?',
        'en' => 'Are you sure you want to reject this member?',
        'zu' => 'Uqinisekile ukuthi ufuna ukwenqaba lelilungu?',
        'xh' => 'Uqinisekile ukuba ufuna ukwala eli lungu?',
        'pt' => 'Tem a certeza de que quer rejeitar este membro?',
        'st' => 'Na u kholisehile hore u batla ho hana setho sena?'
    ],
    'adm_confirm_spouse_link' => [
        'af' => 'Koppel hierdie twee lidmate as huweliksmaats?',
        'en' => 'Link these two members as spouses?',
        'zu' => 'Uyabahlanganisa laba bangamalungu ababili njengabalingani bomshado?',
        'xh' => 'Ubadibanisa aba balungu bobabini njengabalingane bomtshato?',
        'pt' => 'Ligar estes dois membros como cônjuges?',
        'st' => 'Hokahanya ba babeli ba setho e le balekane ba lenyalo?'
    ],
    'adm_confirm_spouse_reject' => [
        'af' => 'Is jy seker jy wil hierdie huweliksversoek afwys?',
        'en' => 'Are you sure you want to turn down this marriage request?',
        'zu' => 'Uqinisekile ukuthi ufuna ukwenqaba lesi sicelo somshado?',
        'xh' => 'Uqinisekile ukuba ufuna ukwala esi sicelo somtshato?',
        'pt' => 'Tem a certeza de que quer recusar este pedido de casamento?',
        'st' => 'Na u kholisehile hore u batla ho hana kopo ena ea lenyalo?'
    ],
    'adm_spouse_linked' => [
        'af' => 'Huweliksmaats gekoppel.',
        'en' => 'Spouses linked.',
        'zu' => 'Abalingani bahlanganisiwe.',
        'xh' => 'Abalingane badityanisiwe.',
        'pt' => 'Cônjuges ligados.',
        'st' => 'Balekane ba hokahantšoe.'
    ],
    'adm_spouse_rejected' => [
        'af' => 'Versoek afgewys.',
        'en' => 'Request turned down.',
        'zu' => 'Isicelo senqatshiwe.',
        'xh' => 'Isicelo siwaliwe.',
        'pt' => 'Pedido recusado.',
        'st' => 'Kopo e hanetsoe.'
    ],
    'adm_spouse_unlinked' => [
        'af' => 'Huweliksmaats ontkoppel.',
        'en' => 'Spouses unlinked.',
        'zu' => 'Abalingani bahlukanisiwe.',
        'xh' => 'Abalingane bahlukaniswe.',
        'pt' => 'Cônjuges desligados.',
        'st' => 'Balekane ba arohantšoe.'
    ],
    'adm_pw_min' => [
        'af' => 'Die nuwe wagwoord moet minstens 8 karakters hê.',
        'en' => 'The new password must have at least 8 characters.',
        'zu' => 'Iphasiwedi entsha kumele ibe nezinhlamvu okungenani eziyi-8.',
        'xh' => 'Iphasiwedi entsha kufuneka ibe neempawu ubuncinane ezi-8.',
        'pt' => 'A nova senha deve ter pelo menos 8 caracteres.',
        'st' => 'Phasewete e ncha e tlameha ho ba le litlhaku tse 8 bonyane.'
    ],
    'adm_pw_max' => [
        'af' => 'Die wagwoord mag hoogstens 72 karakters wees.',
        'en' => 'The password can be at most 72 characters.',
        'zu' => 'Iphasiwedi ingaba nezinhlamvu ezingafika ku-72 kuphela.',
        'xh' => 'Iphasiwedi inokuba neempawu ezingafikelela kwi-72 kuphela.',
        'pt' => 'A senha pode ter no máximo 72 caracteres.',
        'st' => 'Phasewete e ka ba le litlhaku tse 72 ka ho fetisisa.'
    ],
    'adm_pw_same' => [
        'af' => 'Die nuwe wagwoord moet verskil van die huidige een.',
        'en' => 'The new password must be different from the current one.',
        'zu' => 'Iphasiwedi entsha kumele yehluke kweyamanje.',
        'xh' => 'Iphasiwedi entsha kufuneka yahluke kweyangoku.',
        'pt' => 'A nova senha deve ser diferente da atual.',
        'st' => 'Phasewete e ncha e tlameha ho fapana le ea hona joale.'
    ],
    'adm_pw_wrong' => [
        'af' => 'Die huidige wagwoord is nie reg nie.',
        'en' => 'The current password is not correct.',
        'zu' => 'Iphasiwedi yamanje ayilungile.',
        'xh' => 'Iphasiwedi yangoku ayichanekanga.',
        'pt' => 'A senha atual não está correta.',
        'st' => 'Phasewete ea hona joale ha e nepahale.'
    ],
    'adm_author' => [
        'af' => 'Skrywer (opsioneel)',
        'en' => 'Author (optional)',
        'zu' => 'Umbhali (akuphoqelekile)',
        'xh' => 'Umbhali (akunyanzelekanga)',
        'pt' => 'Autor (opcional)',
        'st' => 'Mongoli (ha ho qobelloe)'
    ],
    'adm_load_failed' => [
        'af' => 'Kon nie laai nie. Probeer weer.',
        'en' => 'Could not load. Please try again.',
        'zu' => 'Ayilayishekanga. Sicela uzame futhi.',
        'xh' => 'Ayilayishekanga. Nceda uzame kwakhona.',
        'pt' => 'Não foi possível carregar. Tente novamente.',
        'st' => 'Ha ea ka ea laolloa. Ka kopo leka hape.'
    ],
    'adm_chars_left' => [
        'af' => '{n} karakters oor',
        'en' => '{n} characters left',
        'zu' => 'Zisele izinhlamvu: {n}',
        'xh' => 'Ziseleyo iimpawu: {n}',
        'pt' => 'Restam {n} caracteres',
        'st' => 'Ho setse litlhaku tse {n}'
    ],
    'adm_appt_sent' => [
        'af' => 'Afspraakversoek gestuur.',
        'en' => 'Appointment request sent.',
        'zu' => 'Isicelo sokuhlangana sithunyelwe.',
        'xh' => 'Isicelo sokudibana sithunyelwe.',
        'pt' => 'Pedido de marcação enviado.',
        'st' => 'Kopo ea ho kopana e rometsoe.'
    ],
    'adm_appt_confirmed' => [
        'af' => 'Afspraak bevestig.',
        'en' => 'Appointment confirmed.',
        'zu' => 'Ukuhlangana kuqinisekisiwe.',
        'xh' => 'Ukudibana kuqinisekisiwe.',
        'pt' => 'Marcação confirmada.',
        'st' => 'Kopano e netefalitsoe.'
    ],
    'adm_appt_cancelled' => [
        'af' => 'Afspraak gekanselleer.',
        'en' => 'Appointment cancelled.',
        'zu' => 'Ukuhlangana kukhanseliwe.',
        'xh' => 'Ukudibana kurhoxisiwe.',
        'pt' => 'Marcação cancelada.',
        'st' => 'Kopano e hlakotsoe.'
    ],
    'adm_appt_confirm_cancel' => [
        'af' => 'Kanselleer hierdie afspraak?',
        'en' => 'Cancel this appointment?',
        'zu' => 'Khansela lokhu kuhlangana?',
        'xh' => 'Rhoxisa olu dibano?',
        'pt' => 'Cancelar esta marcação?',
        'st' => 'Hlakola kopano ena?'
    ],
    'adm_appt_pick' => [
        'af' => 'Kies \'n datum en \'n tyd.',
        'en' => 'Choose a date and a time.',
        'zu' => 'Khetha usuku nesikhathi.',
        'xh' => 'Khetha umhla nexesha.',
        'pt' => 'Escolha uma data e uma hora.',
        'st' => 'Khetha letsatsi le nako.'
    ],
    'adm_appt_upcoming' => [
        'af' => 'Komende afsprake',
        'en' => 'Upcoming appointments',
        'zu' => 'Izicelo ezizayo',
        'xh' => 'Iintlanganiso ezizayo',
        'pt' => 'Próximos compromissos',
        'st' => 'Likopano tse tlang'
    ],
    'adm_pw_show' => [
        'af' => 'Wys wagwoord',
        'en' => 'Show password',
        'zu' => 'Bonisa iphasiwedi',
        'xh' => 'Bonisa iphasiwedi',
        'pt' => 'Mostrar senha',
        'st' => 'Bontša phasewete'
    ],
    'adm_pw_hide' => [
        'af' => 'Versteek wagwoord',
        'en' => 'Hide password',
        'zu' => 'Fihla iphasiwedi',
        'xh' => 'Fihla iphasiwedi',
        'pt' => 'Ocultar senha',
        'st' => 'Pata phasewete'
    ],
    'adm_photo_pick' => [
        'af' => 'Kies \'n foto',
        'en' => 'Choose a photo',
        'zu' => 'Khetha isithombe',
        'xh' => 'Khetha umfanekiso',
        'pt' => 'Escolher uma foto',
        'st' => 'Khetha senepe'
    ],
    'prf_change_office' => [
        'af' => 'Verander amp',
        'en' => 'Change office',
        'zu' => 'Shintsha isikhundla',
        'xh' => 'Tshintsha isikhundla',
        'pt' => 'Alterar cargo',
        'st' => 'Fetola kantoro'
    ],
    'prf_delete_q' => [
        'af' => 'Verwyder {name} se rekening?',
        'en' => 'Delete {name}\'s account?',
        'zu' => 'Susa i-akhawunti ka-{name}?',
        'xh' => 'Cima iakhawunti ka-{name}?',
        'pt' => 'Eliminar a conta de {name}?',
        'st' => 'Hlakola akhaonto ea {name}?'
    ],
    'adm_acc_bad_birthdate' => [
        'af' => 'Daardie geboortedatum is nie geldig nie.',
        'en' => 'That birth date is not valid.',
        'zu' => 'Usuku lokuzalwa alulungile.',
        'xh' => 'Umhla wokuzalwa awunjalo.',
        'pt' => 'Essa data de nascimento não é válida.',
        'st' => 'Letsatsi lena la tsoalo ha le nepahale.'
    ],
    'adm_acc_name_required' => [
        'af' => 'Vul asseblief jou naam en van in.',
        'en' => 'Please fill in your name and surname.',
        'zu' => 'Sicela ugcwalise igama nesibongo sakho.',
        'xh' => 'Nceda ugcwalise igama nefani lakho.',
        'pt' => 'Preencha o seu nome e apelido.',
        'st' => 'Ka kopo tlatsa lebitso la hau le sefane.'
    ],
    'adm_acc_bad_email' => [
        'af' => 'Daardie e-posadres is nie geldig nie.',
        'en' => 'That email address is not valid.',
        'zu' => 'Leli kheli le-imeyili alilungile.',
        'xh' => 'Eli dilesi ye-imeyile alichanekanga.',
        'pt' => 'Esse e-mail não é válido.',
        'st' => 'Aterese ena ea imeile ha e nepahale.'
    ],
    'adm_acc_email_taken' => [
        'af' => '\'n Ander lid gebruik reeds daardie e-posadres.',
        'en' => 'Another member already uses that email address.',
        'zu' => 'Elinye ilungu selivele lisebenzisa leli kheli le-imeyili.',
        'xh' => 'Elinye ilungu selisebenzisa eli dilesi ye-imeyile.',
        'pt' => 'Outro membro já usa esse e-mail.',
        'st' => 'Setho se seng se se se sebelisa aterese eo ea imeile.'
    ],
    'adm_acc_bad_photo' => [
        'af' => 'Daardie foto kon nie gebruik word nie. Gebruik \'n JPEG-, PNG- of WebP-prent.',
        'en' => 'That photo could not be used. Use a JPEG, PNG or WebP picture.',
        'zu' => 'Leso sithombe asikwazanga ukusetshenziswa. Sebenzisa isithombe se-JPEG, PNG noma WebP.',
        'xh' => 'Loo mfanekiso awukwazanga ukusetyenziswa. Sebenzisa umfanekiso we-JPEG, PNG okanye WebP.',
        'pt' => 'Não foi possível usar essa foto. Use uma imagem JPEG, PNG ou WebP.',
        'st' => 'Setšoantšo seo se ke ke sa sebelisoa. Sebelisa setšoantšo sa JPEG, PNG kapa WebP.'
    ],
    'adm_email' => [
        'af' => 'E-pos',
        'en' => 'Email',
        'zu' => 'I-imeyili',
        'xh' => 'I-imeyile',
        'pt' => 'E-mail',
        'st' => 'Imeile'
    ],
    'adm_crop_title' => [
        'af' => 'Posisioneer jou foto',
        'en' => 'Position your photo',
        'zu' => 'Beka isithombe sakho',
        'xh' => 'Beka umfanekiso wakho',
        'pt' => 'Posicione a sua foto',
        'st' => 'Beha setšoantšo sa hau'
    ],
    'adm_crop_hint' => [
        'af' => 'Sleep om te skuif • gebruik die glyer om te zoem',
        'en' => 'Drag to move • use the slider to zoom',
        'zu' => 'Hudula ukuze unyakaze • sebenzisa isilayidi ukusondeza',
        'xh' => 'Tsala ukuze utshintshe indawo • sebenzisa isilayidi ukusondeza',
        'pt' => 'Arraste para mover • use o cursor para ampliar',
        'st' => 'Hula ho tsamaisa • sebelisa slaeda ho hakanya'
    ],
    'adm_zoom_in' => [
        'af' => 'Zoem in',
        'en' => 'Zoom in',
        'zu' => 'Sondeza',
        'xh' => 'Sondeza',
        'pt' => 'Aproximar',
        'st' => 'Hakanya'
    ],
    'adm_zoom_out' => [
        'af' => 'Zoem uit',
        'en' => 'Zoom out',
        'zu' => 'Hlehlisa',
        'xh' => 'Hlehlisa',
        'pt' => 'Afastar',
        'st' => 'Fokotsa'
    ],
    'adm_photo_chosen' => [
        'af' => 'Foto gereed — stoor om dit te hou.',
        'en' => 'Photo ready — save to keep it.',
        'zu' => 'Isithombe sesilungile — gcina ukusigcina.',
        'xh' => 'Umfanekiso ulungile — gcina ukuwugcina.',
        'pt' => 'Foto pronta — guarde para a manter.',
        'st' => 'Setšoantšo se loketse — boloka ho se boloka.'
    ]

]);

/**
 * Validate and return a supported language code
 * Falls back to 'en' if invalid
 */
function validate_language(string $lang): string {
    $lang = strtolower(trim($lang));
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'en';
}

/**
 * Get the display name for a language code
 */
function get_lang_name(string $lang): string {
    return LANG_NAMES[$lang] ?? LANG_NAMES['en'];
}

/**
 * Get the Bible filename for a language
 */
function get_bible_file(string $lang): string {
    return BIBLE_FILES[$lang] ?? BIBLE_FILES['en'];
}

/**
 * Get a translated UI string
 * @param string $key The translation key
 * @param string $lang The language code
 * @return string The translated string or the English fallback
 */
function __t(string $key, string $lang): string {
    if (!isset(UI_TRANSLATIONS[$key])) {
        return $key;
    }
    return UI_TRANSLATIONS[$key][$lang] ?? UI_TRANSLATIONS[$key]['en'] ?? $key;
}
