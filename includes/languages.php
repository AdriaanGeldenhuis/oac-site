<?php
/**
 * Language Configuration - Single Source of Truth
 * All supported languages for the OAC App
 */
declare(strict_types=1);

// Supported language codes
define('SUPPORTED_LANGS', ['af', 'en', 'zu', 'xh', 'pt']);

// Display names for each language
define('LANG_NAMES', [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Português'
]);

// Bible file mapping per language
define('BIBLE_FILES', [
    'af' => 'af_1933_53.json',
    'en' => 'en_kjv1611.json',
    'zu' => 'zu_dummy.json',
    'xh' => 'xh_dummy.json',
    'pt' => 'pt_dummy.json'
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
        'pt' => 'Bem-vindo'
    ],
    'welcome_title' => [
        'af' => 'Welkom by Die Ou Aposteliese Kerk',
        'en' => 'Welcome to The Old Apostolic Church',
        'zu' => 'Siyakwamukela eBandleni Elidala Labapostoli',
        'xh' => 'Wamkelekile kwiCawe yamaDala amaPostile',
        'pt' => 'Bem-vindo à Igreja Apostólica Antiga'
    ],
    'teaching_of_month' => [
        'af' => 'Lering van die Maand',
        'en' => 'Teaching of the Month',
        'zu' => 'Imfundiso Yenyanga',
        'xh' => 'Imfundiso Yenyanga',
        'pt' => 'Ensino do Mês'
    ],
    'grow_faith' => [
        'af' => 'Groei in geloof en kennis',
        'en' => 'Growing in faith and knowledge',
        'zu' => 'Ukukhula ekukholweni nasekwazini',
        'xh' => 'Ukukhula elukholweni naselwazini',
        'pt' => 'Crescendo em fé e conhecimento'
    ],
    'no_content' => [
        'af' => 'Geen lering-inhoud gevind nie.',
        'en' => 'No teaching content found.',
        'zu' => 'Akukho okuqukethwe okufundisayo okutholiwe.',
        'xh' => 'Akukho mfundiso ifunyenweyo.',
        'pt' => 'Nenhum conteúdo de ensino encontrado.'
    ],
    // Navigation
    'gospel_media' => [
        'af' => 'Evangelie Media',
        'en' => 'Gospel Media',
        'zu' => 'Imidiya YeVangeli',
        'xh' => 'Imidiya yeVangeli',
        'pt' => 'Mídia do Evangelho'
    ],
    'prayers' => [
        'af' => 'Gebede',
        'en' => 'Prayers',
        'zu' => 'Imithandazo',
        'xh' => 'Imithandazo',
        'pt' => 'Orações'
    ],
    'bible' => [
        'af' => 'Bybel',
        'en' => 'Bible',
        'zu' => 'IBhayibheli',
        'xh' => 'IBhayibhile',
        'pt' => 'Bíblia'
    ],
    'calendar' => [
        'af' => 'Kalender',
        'en' => 'Calendar',
        'zu' => 'Ikhalenda',
        'xh' => 'Ikhalenda',
        'pt' => 'Calendário'
    ],
    'diary' => [
        'af' => 'Dagboek',
        'en' => 'Diary',
        'zu' => 'Idayari',
        'xh' => 'Idayari',
        'pt' => 'Diário'
    ],
    'notifications' => [
        'af' => 'Kennisgewings',
        'en' => 'Notifications',
        'zu' => 'Izaziso',
        'xh' => 'Izaziso',
        'pt' => 'Notificações'
    ],
    'admin' => [
        'af' => 'Admin',
        'en' => 'Admin',
        'zu' => 'Umlawuli',
        'xh' => 'Umlawuli',
        'pt' => 'Admin'
    ],
    'logout' => [
        'af' => 'Teken uit',
        'en' => 'Log out',
        'zu' => 'Phuma',
        'xh' => 'Phuma',
        'pt' => 'Sair'
    ],
    'navigation' => [
        'af' => 'Navigasie',
        'en' => 'Navigation',
        'zu' => 'Ukuzulazula',
        'xh' => 'Ukuhamba',
        'pt' => 'Navegação'
    ],
    'close' => [
        'af' => 'Sluit',
        'en' => 'Close',
        'zu' => 'Vala',
        'xh' => 'Vala',
        'pt' => 'Fechar'
    ],
    'open_menu' => [
        'af' => 'Open menu',
        'en' => 'Open menu',
        'zu' => 'Vula imenyu',
        'xh' => 'Vula imenyu',
        'pt' => 'Abrir menu'
    ],
    'toggle_theme' => [
        'af' => 'Wissel Tema',
        'en' => 'Toggle Theme',
        'zu' => 'Shintsha Ithimu',
        'xh' => 'Tshintsha Ithimu',
        'pt' => 'Alternar Tema'
    ],
    'toggle_view' => [
        'af' => 'Wissel Aansig',
        'en' => 'Toggle View',
        'zu' => 'Shintsha Umbono',
        'xh' => 'Tshintsha Imbonakalo',
        'pt' => 'Alternar Visualização'
    ],
    'ai_smart_bible' => [
        'af' => 'AI Slimbybel',
        'en' => 'AI Smart Bible',
        'zu' => 'IBhayibheli Elihlakaniphile le-AI',
        'xh' => 'IBhayibhile eKreleyayo ye-AI',
        'pt' => 'Bíblia Inteligente IA'
    ],
    'sing_emmanuel' => [
        'af' => 'Sing Emmanuel',
        'en' => 'Sing Emmanuel',
        'zu' => 'Cula Emmanuel',
        'xh' => 'Cula Emmanuel',
        'pt' => 'Cantar Emmanuel'
    ],
    // Page titles
    'page_title_welcome' => [
        'af' => 'Welkom - OAC APP',
        'en' => 'Welcome - OAC APP',
        'zu' => 'Siyakwamukela - OAC APP',
        'xh' => 'Wamkelekile - OAC APP',
        'pt' => 'Bem-vindo - OAC APP'
    ],
    // Scripture quotes
    'quote_matthew_18_20' => [
        'af' => 'Want waar twee of drie in My Naam vergader is, daar is Ek in hulle midde.',
        'en' => 'For where two or three gather in my name, there am I with them.',
        'zu' => 'Ngoba lapho ababili noma abathathu behlangene egameni lami, ngikhona phakathi kwabo.',
        'xh' => 'Kuba apho kubandakanya ababini okanye abathathu egameni lam, ndilapho phakathi kwabo.',
        'pt' => 'Pois onde dois ou três estão reunidos em meu nome, ali estou eu no meio deles.'
    ],
    'quote_matthew_18_20_ref' => [
        'af' => 'Matthéüs 18:20',
        'en' => 'Matthew 18:20',
        'zu' => 'UMathewu 18:20',
        'xh' => 'UMateyu 18:20',
        'pt' => 'Mateus 18:20'
    ],

    // ==================== ADMIN SECTION ====================
    'admin_dashboard' => [
        'af' => 'Admin Bestuur',
        'en' => 'Admin Dashboard',
        'zu' => 'Iphaneli Yokuphatha',
        'xh' => 'Iphaneli Yolawulo',
        'pt' => 'Painel Admin'
    ],
    'admin_subtitle' => [
        'af' => 'Beheer jou profiel, goedkeurings en instellings',
        'en' => 'Manage your profile, approvals and settings',
        'zu' => 'Phatha iphrofayela yakho, izimvume nezilungiselelo',
        'xh' => 'Lawula iprofayile yakho, imvume kunye neesetingi',
        'pt' => 'Gerencie seu perfil, aprovações e configurações'
    ],
    'profile' => [
        'af' => 'Profiel',
        'en' => 'Profile',
        'zu' => 'Iphrofayela',
        'xh' => 'Iprofayile',
        'pt' => 'Perfil'
    ],
    'accounts' => [
        'af' => 'Rekeninge',
        'en' => 'Accounts',
        'zu' => 'Ama-akhawunti',
        'xh' => 'Iiakhawunti',
        'pt' => 'Contas'
    ],
    'teaching' => [
        'af' => 'Lering',
        'en' => 'Teaching',
        'zu' => 'Imfundiso',
        'xh' => 'Imfundiso',
        'pt' => 'Ensino'
    ],
    'offices' => [
        'af' => 'Ampte',
        'en' => 'Offices',
        'zu' => 'Izihamba',
        'xh' => 'Iiofisi',
        'pt' => 'Cargos'
    ],
    'appointments' => [
        'af' => 'Afsprake',
        'en' => 'Appointments',
        'zu' => 'Izicelo',
        'xh' => 'Iintlanganiso',
        'pt' => 'Compromissos'
    ],
    'settings' => [
        'af' => 'Instellings',
        'en' => 'Settings',
        'zu' => 'Izilungiselelo',
        'xh' => 'Iisetingi',
        'pt' => 'Configurações'
    ],
    'approvals' => [
        'af' => 'Goedkeurings',
        'en' => 'Approvals',
        'zu' => 'Izimvume',
        'xh' => 'Imvume',
        'pt' => 'Aprovações'
    ],
    'edit_profile' => [
        'af' => 'Wysig Profiel',
        'en' => 'Edit Profile',
        'zu' => 'Hlela Iphrofayela',
        'xh' => 'Hlela Iprofayile',
        'pt' => 'Editar Perfil'
    ],
    'update_personal_info' => [
        'af' => 'Werk jou persoonlike inligting by',
        'en' => 'Update your personal information',
        'zu' => 'Buyekeza ulwazi lwakho lomuntu siqu',
        'xh' => 'Hlaziya ulwazi lwakho lobuqu',
        'pt' => 'Atualize suas informações pessoais'
    ],
    'spouse_request_sent' => [
        'af' => 'Eggenoot versoek gestuur. Wag vir goedkeuring.',
        'en' => 'Spouse request sent. Waiting for approval.',
        'zu' => 'Isicelo somshado sithunyelwe. Ilinde ukuvunywa.',
        'xh' => 'Isicelo somyeni sithunyelwe. Silindele ukuvunywa.',
        'pt' => 'Pedido de cônjuge enviado. Aguardando aprovação.'
    ],
    'profile_updated' => [
        'af' => 'Profiel suksesvol opgedateer',
        'en' => 'Profile updated successfully',
        'zu' => 'Iphrofayela ibuyekezwe ngempumelelo',
        'xh' => 'Iprofayile ihlaziywe ngempumelelo',
        'pt' => 'Perfil atualizado com sucesso'
    ],
    'error' => [
        'af' => 'Fout',
        'en' => 'Error',
        'zu' => 'Iphutha',
        'xh' => 'Impazamo',
        'pt' => 'Erro'
    ],
    'profile_no_town' => [
        'af' => 'Jou profiel het geen dorp nie. Kontak admin.',
        'en' => 'Your profile has no town. Contact admin.',
        'zu' => 'Iphrofayela yakho ayinalo idolobha. Xhumana no-admin.',
        'xh' => 'Iprofayile yakho ayinayo idolophu. Qhagamshelana no-admin.',
        'pt' => 'Seu perfil não tem cidade. Contate o administrador.'
    ],
    'you_sent_spouse_request' => [
        'af' => 'Jy het \'n eggenoot versoek gestuur na',
        'en' => 'You sent a spouse request to',
        'zu' => 'Uthumele isicelo somshado ku',
        'xh' => 'Uthumele isicelo somyeni ku',
        'pt' => 'Você enviou um pedido de cônjuge para'
    ],
    'waiting_approval' => [
        'af' => 'Wag vir goedkeuring.',
        'en' => 'Waiting for approval.',
        'zu' => 'Ilinde ukuvunywa.',
        'xh' => 'Ilindele ukuvunywa.',
        'pt' => 'Aguardando aprovação.'
    ],
    'wants_to_marry_you' => [
        'af' => 'wil met jou trou.',
        'en' => 'wants to marry you.',
        'zu' => 'ufuna ukushada nawe.',
        'xh' => 'ufuna ukutshata nawe.',
        'pt' => 'quer se casar com você.'
    ],
    'accept' => [
        'af' => 'Aanvaar',
        'en' => 'Accept',
        'zu' => 'Yamukela',
        'xh' => 'Yamkela',
        'pt' => 'Aceitar'
    ],
    'reject' => [
        'af' => 'Verwerp',
        'en' => 'Reject',
        'zu' => 'Yenqaba',
        'xh' => 'Yala',
        'pt' => 'Rejeitar'
    ],
    'profile_photo' => [
        'af' => 'Profiel Foto',
        'en' => 'Profile Photo',
        'zu' => 'Isithombe Sephrofayela',
        'xh' => 'Ifoto Yeprofayile',
        'pt' => 'Foto do Perfil'
    ],
    'photo_hint' => [
        'af' => 'Kies \'n foto om op te laai. Die foto sal outomaties 600x600px wees.',
        'en' => 'Choose a photo to upload. The photo will be automatically resized to 600x600px.',
        'zu' => 'Khetha isithombe ozosifaka. Isithombe sizoshintshwa ngokuzenzakalela sibe 600x600px.',
        'xh' => 'Khetha ifoto ozoyilayisha. Ifoto iya kutshintshelwa ngokuzenzekelayo ku-600x600px.',
        'pt' => 'Escolha uma foto para enviar. A foto será redimensionada automaticamente para 600x600px.'
    ],
    'name' => [
        'af' => 'Naam',
        'en' => 'Name',
        'zu' => 'Igama',
        'xh' => 'Igama',
        'pt' => 'Nome'
    ],
    'surname' => [
        'af' => 'Van',
        'en' => 'Surname',
        'zu' => 'Isibongo',
        'xh' => 'Ifani',
        'pt' => 'Sobrenome'
    ],
    'phone' => [
        'af' => 'Selfoon',
        'en' => 'Phone',
        'zu' => 'Ucingo',
        'xh' => 'Ifowuni',
        'pt' => 'Telefone'
    ],
    'language' => [
        'af' => 'Taal',
        'en' => 'Language',
        'zu' => 'Ulimi',
        'xh' => 'Ulwimi',
        'pt' => 'Idioma'
    ],
    'birthdate' => [
        'af' => 'Geboortedatum',
        'en' => 'Birthdate',
        'zu' => 'Usuku Lokuzalwa',
        'xh' => 'Umhla Wokuzalwa',
        'pt' => 'Data de Nascimento'
    ],
    'marital_status' => [
        'af' => 'Huwelikstatus',
        'en' => 'Marital Status',
        'zu' => 'Isimo Somshado',
        'xh' => 'Imeko Yomtshato',
        'pt' => 'Estado Civil'
    ],
    'select' => [
        'af' => 'Kies',
        'en' => 'Select',
        'zu' => 'Khetha',
        'xh' => 'Khetha',
        'pt' => 'Selecionar'
    ],
    'married' => [
        'af' => 'Getroud',
        'en' => 'Married',
        'zu' => 'Ushadile',
        'xh' => 'Utshatile',
        'pt' => 'Casado(a)'
    ],
    'unmarried' => [
        'af' => 'Ongetroud',
        'en' => 'Unmarried',
        'zu' => 'Awushadile',
        'xh' => 'Awutshatanga',
        'pt' => 'Solteiro(a)'
    ],
    'male' => [
        'af' => 'Man',
        'en' => 'Male',
        'zu' => 'Owesilisa',
        'xh' => 'Indoda',
        'pt' => 'Masculino'
    ],
    'female' => [
        'af' => 'Vrou',
        'en' => 'Female',
        'zu' => 'Owesifazane',
        'xh' => 'Umfazi',
        'pt' => 'Feminino'
    ],
    'man' => [
        'af' => 'Man',
        'en' => 'Male',
        'zu' => 'Owesilisa',
        'xh' => 'Indoda',
        'pt' => 'Masculino'
    ],
    'vrou' => [
        'af' => 'Vrou',
        'en' => 'Female',
        'zu' => 'Owesifazane',
        'xh' => 'Umfazi',
        'pt' => 'Feminino'
    ],
    'getroud' => [
        'af' => 'Getroud',
        'en' => 'Married',
        'zu' => 'Ushadile',
        'xh' => 'Utshatile',
        'pt' => 'Casado(a)'
    ],
    'ongetroud' => [
        'af' => 'Ongetroud',
        'en' => 'Unmarried',
        'zu' => 'Awushadile',
        'xh' => 'Awutshatanga',
        'pt' => 'Solteiro(a)'
    ],
    'widowed' => [
        'af' => 'Wewenaar/Weduwee',
        'en' => 'Widowed',
        'zu' => 'Umfelokazi',
        'xh' => 'Umhlolokazi',
        'pt' => 'Viúvo(a)'
    ],
    'divorced' => [
        'af' => 'Geskei',
        'en' => 'Divorced',
        'zu' => 'Ohlukanisile',
        'xh' => 'Oqhawukileyo',
        'pt' => 'Divorciado(a)'
    ],
    'province' => [
        'af' => 'Provinsie',
        'en' => 'Province',
        'zu' => 'Isifundazwe',
        'xh' => 'IPhondo',
        'pt' => 'Província'
    ],
    'town_city' => [
        'af' => 'Stad/Dorp',
        'en' => 'Town/City',
        'zu' => 'Idolobha',
        'xh' => 'Idolophu',
        'pt' => 'Cidade'
    ],
    'congregation' => [
        'af' => 'Gemeente',
        'en' => 'Congregation',
        'zu' => 'Ibandla',
        'xh' => 'Ibandla',
        'pt' => 'Congregação'
    ],
    'oversight' => [
        'af' => 'Opsienerskap',
        'en' => 'Oversight',
        'zu' => 'Ukuqashwa',
        'xh' => 'Ukongamela',
        'pt' => 'Supervisão'
    ],
    'youth' => [
        'af' => 'Jeug',
        'en' => 'Youth',
        'zu' => 'Intsha',
        'xh' => 'Ulutsha',
        'pt' => 'Juventude'
    ],
    'sunday_school' => [
        'af' => 'Sondagskool',
        'en' => 'Sunday School',
        'zu' => 'Isikole SeSonto',
        'xh' => 'Isikolo SeCawa',
        'pt' => 'Escola Dominical'
    ],
    'room' => [
        'af' => 'Kamer',
        'en' => 'Room',
        'zu' => 'Ikamelo',
        'xh' => 'Igumbi',
        'pt' => 'Sala'
    ],
    'select_province_first' => [
        'af' => 'Kies provinsie eers',
        'en' => 'Select province first',
        'zu' => 'Khetha isifundazwe kuqala',
        'xh' => 'Khetha iphondo kuqala',
        'pt' => 'Selecione a província primeiro'
    ],
    'select_town_first' => [
        'af' => 'Kies dorp eers',
        'en' => 'Select town first',
        'zu' => 'Khetha idolobha kuqala',
        'xh' => 'Khetha idolophu kuqala',
        'pt' => 'Selecione a cidade primeiro'
    ],
    'spouse' => [
        'af' => 'Eggenoot/Eggenote',
        'en' => 'Spouse',
        'zu' => 'Umlingani',
        'xh' => 'Umlingani',
        'pt' => 'Cônjuge'
    ],
    'spouse_hint' => [
        'af' => 'Stuur \'n versoek om jou eggenoot te koppel. Die ander persoon moet goedkeur.',
        'en' => 'Send a request to link your spouse. The other person must approve.',
        'zu' => 'Thumela isicelo sokuxhumanisa umlingani wakho. Omunye umuntu kufanele avume.',
        'xh' => 'Thumela isicelo sokudibanisa umlingani wakho. Omnye umntu kufuneka avume.',
        'pt' => 'Envie um pedido para vincular seu cônjuge. A outra pessoa deve aprovar.'
    ],
    'you_are_linked_to' => [
        'af' => 'Jy is gekoppel aan',
        'en' => 'You are linked to',
        'zu' => 'Uxhunyiwe no',
        'xh' => 'Udibanisiwe no',
        'pt' => 'Você está vinculado a'
    ],
    'about' => [
        'af' => 'Oor',
        'en' => 'About',
        'zu' => 'Mayelana',
        'xh' => 'Malunga',
        'pt' => 'Sobre'
    ],
    'about_placeholder' => [
        'af' => 'Vertel ons meer van jouself',
        'en' => 'Tell us more about yourself',
        'zu' => 'Sitshele okwengeziwe ngawe',
        'xh' => 'Sixelele ngakumbi ngawe',
        'pt' => 'Conte-nos mais sobre você'
    ],
    'cancel' => [
        'af' => 'Kanselleer',
        'en' => 'Cancel',
        'zu' => 'Khansela',
        'xh' => 'Rhoxisa',
        'pt' => 'Cancelar'
    ],
    'save' => [
        'af' => 'Stoor',
        'en' => 'Save',
        'zu' => 'Gcina',
        'xh' => 'Gcina',
        'pt' => 'Salvar'
    ],

    // ==================== CHURCH OFFICES (Ampte) ====================
    'apostle' => [
        'af' => 'Apostel',
        'en' => 'Apostle',
        'zu' => 'Umphostoli',
        'xh' => 'UmPostile',
        'pt' => 'Apóstolo'
    ],
    'prophet' => [
        'af' => 'Profeet',
        'en' => 'Prophet',
        'zu' => 'Umprofethi',
        'xh' => 'Umprofeti',
        'pt' => 'Profeta'
    ],
    'evangelist' => [
        'af' => 'Evangelis',
        'en' => 'Evangelist',
        'zu' => 'Umvangeli',
        'xh' => 'Umvangeli',
        'pt' => 'Evangelista'
    ],
    'shepherd' => [
        'af' => 'Herder',
        'en' => 'Shepherd',
        'zu' => 'Umalusi',
        'xh' => 'Umalusi',
        'pt' => 'Pastor'
    ],
    'teacher' => [
        'af' => 'Leraar',
        'en' => 'Teacher',
        'zu' => 'Umfundisi',
        'xh' => 'Umfundisi',
        'pt' => 'Professor'
    ],
    'elder' => [
        'af' => 'Oudste',
        'en' => 'Elder',
        'zu' => 'Umdala',
        'xh' => 'Umdala',
        'pt' => 'Ancião'
    ],
    'overseer' => [
        'af' => 'Opsiener',
        'en' => 'Overseer',
        'zu' => 'Umbonisi',
        'xh' => 'Umbonisi',
        'pt' => 'Supervisor'
    ],
    'deacon' => [
        'af' => 'Diaken',
        'en' => 'Deacon',
        'zu' => 'Umdiakoni',
        'xh' => 'Umdiakoni',
        'pt' => 'Diácono'
    ],
    'deaconess' => [
        'af' => 'Diakonesse',
        'en' => 'Deaconess',
        'zu' => 'Umdiakoni wesifazane',
        'xh' => 'Umdiakoni obhinqileyo',
        'pt' => 'Diaconisa'
    ],
    'priest' => [
        'af' => 'Priester',
        'en' => 'Priest',
        'zu' => 'Umpristi',
        'xh' => 'Umbingeleli',
        'pt' => 'Sacerdote'
    ],
    'priestess' => [
        'af' => 'Priesteres',
        'en' => 'Priestess',
        'zu' => 'Umpristi wesifazane',
        'xh' => 'Umbingeleli obhinqileyo',
        'pt' => 'Sacerdotisa'
    ],
    'member' => [
        'af' => 'Lidmaat',
        'en' => 'Member',
        'zu' => 'Ilunga',
        'xh' => 'Ilungu',
        'pt' => 'Membro'
    ],
    'apostle_sister' => [
        'af' => 'Apostel Suster',
        'en' => 'Apostle Sister',
        'zu' => 'Udade woMphostoli',
        'xh' => 'Udade woMpostile',
        'pt' => 'Irmã Apóstola'
    ],
    'overseer_sister' => [
        'af' => 'Opsiener Suster',
        'en' => 'Overseer Sister',
        'zu' => 'Udade woMbonisi',
        'xh' => 'Udade woMbonisi',
        'pt' => 'Irmã Supervisora'
    ],
    'evangelist_sister' => [
        'af' => 'Evangelis Suster',
        'en' => 'Evangelist Sister',
        'zu' => 'Udade woMvangeli',
        'xh' => 'Udade woMvangeli',
        'pt' => 'Irmã Evangelista'
    ],
    'prophet_sister' => [
        'af' => 'Profeet Suster',
        'en' => 'Prophet Sister',
        'zu' => 'Udade woMprofethi',
        'xh' => 'Udade woMprofeti',
        'pt' => 'Irmã Profetisa'
    ],
    'elder_sister' => [
        'af' => 'Oudste Suster',
        'en' => 'Elder Sister',
        'zu' => 'Udade woMdala',
        'xh' => 'Udade woMdala',
        'pt' => 'Irmã Anciã'
    ],
    'priest_sister' => [
        'af' => 'Priester Suster',
        'en' => 'Priest Sister',
        'zu' => 'Udade woMpristi',
        'xh' => 'Udade woMbingeleli',
        'pt' => 'Irmã Sacerdotisa'
    ],
    'subdeacon' => [
        'af' => 'Onderdiaken',
        'en' => 'Subdeacon',
        'zu' => 'Umdiakoni omncane',
        'xh' => 'Umdiakoni omncinane',
        'pt' => 'Subdiácono'
    ],
    'subdeacon_sister' => [
        'af' => 'Onderdiaken Suster',
        'en' => 'Subdeacon Sister',
        'zu' => 'Udade woMdiakoni omncane',
        'xh' => 'Udade woMdiakoni omncinane',
        'pt' => 'Irmã Subdiaconisa'
    ],
    'choir_leader_brother' => [
        'af' => 'Koorleier Broer',
        'en' => 'Choir Leader Brother',
        'zu' => 'Umfowethu oHola iKwaya',
        'xh' => 'Umzalwana oKhokela iKwayara',
        'pt' => 'Irmão Regente do Coro'
    ],
    'choir_leader_sister' => [
        'af' => 'Koorleier Suster',
        'en' => 'Choir Leader Sister',
        'zu' => 'Udade oHola iKwaya',
        'xh' => 'Udade oKhokela iKwayara',
        'pt' => 'Irmã Regente do Coro'
    ],
    'helper' => [
        'af' => 'Hulpkrag',
        'en' => 'Helper',
        'zu' => 'Umsizi',
        'xh' => 'Umncedisi',
        'pt' => 'Ajudante'
    ],
    'helper_sister' => [
        'af' => 'Hulpkrag Suster',
        'en' => 'Helper Sister',
        'zu' => 'Udade oSizayo',
        'xh' => 'Udade oNcedisayo',
        'pt' => 'Irmã Ajudante'
    ],
    'brother' => [
        'af' => 'Broer',
        'en' => 'Brother',
        'zu' => 'Umfowethu',
        'xh' => 'Umzalwana',
        'pt' => 'Irmão'
    ],
    'sister' => [
        'af' => 'Suster',
        'en' => 'Sister',
        'zu' => 'Udade',
        'xh' => 'Udade',
        'pt' => 'Irmã'
    ],

    // ==================== COMMON ACTIONS ====================
    'delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Excluir'
    ],
    'edit' => [
        'af' => 'Wysig',
        'en' => 'Edit',
        'zu' => 'Hlela',
        'xh' => 'Hlela',
        'pt' => 'Editar'
    ],
    'view' => [
        'af' => 'Bekyk',
        'en' => 'View',
        'zu' => 'Buka',
        'xh' => 'Jonga',
        'pt' => 'Ver'
    ],
    'search' => [
        'af' => 'Soek',
        'en' => 'Search',
        'zu' => 'Sesha',
        'xh' => 'Khangela',
        'pt' => 'Pesquisar'
    ],
    'add' => [
        'af' => 'Voeg by',
        'en' => 'Add',
        'zu' => 'Engeza',
        'xh' => 'Yongeza',
        'pt' => 'Adicionar'
    ],
    'confirm' => [
        'af' => 'Bevestig',
        'en' => 'Confirm',
        'zu' => 'Qinisekisa',
        'xh' => 'Qinisekisa',
        'pt' => 'Confirmar'
    ],
    'approve' => [
        'af' => 'Goedkeur',
        'en' => 'Approve',
        'zu' => 'Vuma',
        'xh' => 'Vuma',
        'pt' => 'Aprovar'
    ],
    'loading' => [
        'af' => 'Laai...',
        'en' => 'Loading...',
        'zu' => 'Iyalayisha...',
        'xh' => 'Iyalayisha...',
        'pt' => 'Carregando...'
    ],
    'no_results' => [
        'af' => 'Geen resultate nie',
        'en' => 'No results',
        'zu' => 'Akukho miphumela',
        'xh' => 'Akukho ziphumo',
        'pt' => 'Sem resultados'
    ],
    'success' => [
        'af' => 'Suksesvol',
        'en' => 'Success',
        'zu' => 'Impumelelo',
        'xh' => 'Impumelelo',
        'pt' => 'Sucesso'
    ],
    'failed' => [
        'af' => 'Misluk',
        'en' => 'Failed',
        'zu' => 'Kwehlulekile',
        'xh' => 'Ayiphumelelanga',
        'pt' => 'Falhou'
    ],

    // ==================== ELDERS / TEACHING ====================
    'edit_teaching' => [
        'af' => 'Wysig Lering',
        'en' => 'Edit Teaching',
        'zu' => 'Hlela Imfundiso',
        'xh' => 'Hlela Imfundiso',
        'pt' => 'Editar Ensino'
    ],
    'monthly_teaching' => [
        'af' => 'Maandelikse Lering',
        'en' => 'Monthly Teaching',
        'zu' => 'Imfundiso Yenyanga',
        'xh' => 'Imfundiso Yenyanga',
        'pt' => 'Ensino Mensal'
    ],
    'font' => [
        'af' => 'Lettertipe',
        'en' => 'Font',
        'zu' => 'Uhlobo Lwesibhalo',
        'xh' => 'Uhlobo Lwesibhalo',
        'pt' => 'Fonte'
    ],
    'size' => [
        'af' => 'Grootte',
        'en' => 'Size',
        'zu' => 'Usayizi',
        'xh' => 'Ubungakanani',
        'pt' => 'Tamanho'
    ],
    'color' => [
        'af' => 'Kleur',
        'en' => 'Color',
        'zu' => 'Umbala',
        'xh' => 'Umbala',
        'pt' => 'Cor'
    ],
    'add_verse' => [
        'af' => 'Voeg Vers By',
        'en' => 'Add Verse',
        'zu' => 'Engeza Ivesi',
        'xh' => 'Yongeza Ivesi',
        'pt' => 'Adicionar Versículo'
    ],
    'improve' => [
        'af' => 'Verbeter',
        'en' => 'Improve',
        'zu' => 'Thuthukisa',
        'xh' => 'Phucula',
        'pt' => 'Melhorar'
    ],
    'translate' => [
        'af' => 'Vertaal',
        'en' => 'Translate',
        'zu' => 'Humusha',
        'xh' => 'Guqulela',
        'pt' => 'Traduzir'
    ],
    'translate_all' => [
        'af' => 'Vertaal Alles',
        'en' => 'Translate All',
        'zu' => 'Humusha Konke',
        'xh' => 'Guqulela Konke',
        'pt' => 'Traduzir Tudo'
    ],
    'ready_to_save' => [
        'af' => 'Gereed om te stoor',
        'en' => 'Ready to save',
        'zu' => 'Kulungele ukugcina',
        'xh' => 'Kulungele ukugcina',
        'pt' => 'Pronto para salvar'
    ],
    'save_all' => [
        'af' => 'Stoor Alles',
        'en' => 'Save All',
        'zu' => 'Gcina Konke',
        'xh' => 'Gcina Konke',
        'pt' => 'Salvar Tudo'
    ],
    'add_bible_verse' => [
        'af' => 'Voeg Bybelvers By',
        'en' => 'Add Bible Verse',
        'zu' => 'Engeza Ivesi LeBhayibheli',
        'xh' => 'Yongeza Ivesi LeBhayibhile',
        'pt' => 'Adicionar Versículo Bíblico'
    ],
    'book' => [
        'af' => 'Boek',
        'en' => 'Book',
        'zu' => 'Incwadi',
        'xh' => 'Incwadi',
        'pt' => 'Livro'
    ],
    'chapter' => [
        'af' => 'Hoofstuk',
        'en' => 'Chapter',
        'zu' => 'Isahluko',
        'xh' => 'Isahluko',
        'pt' => 'Capítulo'
    ],
    'from' => [
        'af' => 'Van',
        'en' => 'From',
        'zu' => 'Kusuka',
        'xh' => 'Ukusuka',
        'pt' => 'De'
    ],
    'to' => [
        'af' => 'Tot',
        'en' => 'To',
        'zu' => 'Kuya',
        'xh' => 'Ukuya',
        'pt' => 'Até'
    ],
    'insert' => [
        'af' => 'Voeg In',
        'en' => 'Insert',
        'zu' => 'Faka',
        'xh' => 'Faka',
        'pt' => 'Inserir'
    ],
    'my_profile' => [
        'af' => 'My Profiel',
        'en' => 'My Profile',
        'zu' => 'Iphrofayela Yami',
        'xh' => 'Iprofayile Yam',
        'pt' => 'Meu Perfil'
    ],

    // ==================== ACCOUNTS & SETTINGS ====================
    'accounts_billing' => [
        'af' => 'Rekeninge & Betalings',
        'en' => 'Accounts & Billing',
        'zu' => 'Ama-akhawunti & Ukukhokhela',
        'xh' => 'Iiakhawunti & Ukuhlawula',
        'pt' => 'Contas e Faturamento'
    ],
    'no_billing_history' => [
        'af' => 'Geen betalingsgeskiedenis beskikbaar nie.',
        'en' => 'No billing history available.',
        'zu' => 'Akukho mlando wokukhokha otholakalayo.',
        'xh' => 'Akukho mbali yokuhlawula efumanekayo.',
        'pt' => 'Nenhum histórico de faturamento disponível.'
    ],
    'current_password' => [
        'af' => 'Huidige Wagwoord',
        'en' => 'Current Password',
        'zu' => 'Iphasiwedi Yamanje',
        'xh' => 'Iphasiwedi Yangoku',
        'pt' => 'Senha Atual'
    ],
    'new_password' => [
        'af' => 'Nuwe Wagwoord',
        'en' => 'New Password',
        'zu' => 'Iphasiwedi Entsha',
        'xh' => 'Iphasiwedi Entsha',
        'pt' => 'Nova Senha'
    ],
    'confirm_password' => [
        'af' => 'Bevestig Wagwoord',
        'en' => 'Confirm Password',
        'zu' => 'Qinisekisa Iphasiwedi',
        'xh' => 'Qinisekisa Iphasiwedi',
        'pt' => 'Confirmar Senha'
    ],
    'change_password' => [
        'af' => 'Verander Wagwoord',
        'en' => 'Change Password',
        'zu' => 'Shintsha Iphasiwedi',
        'xh' => 'Tshintsha Iphasiwedi',
        'pt' => 'Alterar Senha'
    ],
    'passwords_no_match' => [
        'af' => 'Wagwoorde stem nie ooreen nie',
        'en' => 'Passwords do not match',
        'zu' => 'Amaphasiwedi awafani',
        'xh' => 'Iiphasiwedi azifani',
        'pt' => 'As senhas não correspondem'
    ],
    'password_min_length' => [
        'af' => 'Wagwoord moet minstens 6 karakters wees',
        'en' => 'Password must be at least 6 characters',
        'zu' => 'Iphasiwedi kufanele ibe okungenani izinhlamvu ezingu-6',
        'xh' => 'Iphasiwedi kufuneka ibe ubuncinane iinimizi ezintandathu',
        'pt' => 'A senha deve ter pelo menos 6 caracteres'
    ],
    'password_changed' => [
        'af' => 'Wagwoord suksesvol verander',
        'en' => 'Password changed successfully',
        'zu' => 'Iphasiwedi ishintshwe ngempumelelo',
        'xh' => 'Iphasiwedi itshintshwe ngempumelelo',
        'pt' => 'Senha alterada com sucesso'
    ],
    'password_change_failed' => [
        'af' => 'Kon nie wagwoord verander nie',
        'en' => 'Could not change password',
        'zu' => 'Ayikwazanga ukushintsha iphasiwedi',
        'xh' => 'Ayikwazanga ukutshintsha iphasiwedi',
        'pt' => 'Não foi possível alterar a senha'
    ],
    'network_error' => [
        'af' => 'Netwerkfout',
        'en' => 'Network error',
        'zu' => 'Iphutha lenethiwekhi',
        'xh' => 'Impazamo yenethiwekhi',
        'pt' => 'Erro de rede'
    ],

    // ==================== APPROVALS TAB ====================
    'user_approvals' => [
        'af' => 'Gebruiker Goedkeurings',
        'en' => 'User Approvals',
        'zu' => 'Izimvume Zabasebenzisi',
        'xh' => 'Imvume Yabasebenzisi',
        'pt' => 'Aprovações de Usuários'
    ],
    'no_pending_approvals' => [
        'af' => 'Geen hangende gebruiker goedkeurings nie.',
        'en' => 'No pending user approvals.',
        'zu' => 'Azikho izimvume zabasebenzisi ezilindile.',
        'xh' => 'Akukho mvume yabasebenzisi elindileyo.',
        'pt' => 'Nenhuma aprovação de usuário pendente.'
    ],
    'role' => [
        'af' => 'Amp',
        'en' => 'Role',
        'zu' => 'Isikhundla',
        'xh' => 'Isikhundla',
        'pt' => 'Cargo'
    ],
    'actions' => [
        'af' => 'Aksies',
        'en' => 'Actions',
        'zu' => 'Izenzo',
        'xh' => 'Izenzo',
        'pt' => 'Ações'
    ],
    'spouse_requests' => [
        'af' => 'Eggenoot Versoeke',
        'en' => 'Spouse Requests',
        'zu' => 'Izicelo Zomlingani',
        'xh' => 'Izicelo Zomlingani',
        'pt' => 'Pedidos de Cônjuge'
    ],
    'no_pending_spouse' => [
        'af' => 'Geen hangende eggenoot versoeke nie.',
        'en' => 'No pending spouse requests.',
        'zu' => 'Azikho izicelo zomlingani ezilindile.',
        'xh' => 'Akukho zicelo zomlingani ezilindileyo.',
        'pt' => 'Nenhum pedido de cônjuge pendente.'
    ],
    'requester' => [
        'af' => 'Versoeker',
        'en' => 'Requester',
        'zu' => 'Umceli',
        'xh' => 'Umceli',
        'pt' => 'Solicitante'
    ],
    'receiver' => [
        'af' => 'Ontvanger',
        'en' => 'Receiver',
        'zu' => 'Umamukeli',
        'xh' => 'Umamkeli',
        'pt' => 'Destinatário'
    ],
    'status' => [
        'af' => 'Status',
        'en' => 'Status',
        'zu' => 'Isimo',
        'xh' => 'Isimo',
        'pt' => 'Status'
    ],
    'pending' => [
        'af' => 'Hangende',
        'en' => 'Pending',
        'zu' => 'Iyalinda',
        'xh' => 'Iyalinda',
        'pt' => 'Pendente'
    ],
    'spouse_auto_approve_note' => [
        'af' => 'Eggenoot versoeke word outomaties goedgekeur wanneer beide partye aanvaar.',
        'en' => 'Spouse requests are automatically approved when both parties accept.',
        'zu' => 'Izicelo zomlingani zivunywa ngokuzenzakalela uma zonke izinhlangothi zivuma.',
        'xh' => 'Izicelo zomlingani ziyavunywa ngokuzenzekelayo xa zonke iiqela ziyavuma.',
        'pt' => 'Os pedidos de cônjuge são aprovados automaticamente quando ambas as partes aceitam.'
    ],

    // ==================== TEACHING TAB ====================
    'teaching_management' => [
        'af' => 'Lering Bestuur',
        'en' => 'Teaching Management',
        'zu' => 'Ukuphathwa Kwemfundiso',
        'xh' => 'Ulawulo Lwemfundiso',
        'pt' => 'Gerenciamento de Ensino'
    ],
    'edit_teaching_desc' => [
        'af' => 'Wysig die maandelikse lering vir jou dorp en gemeente.',
        'en' => 'Edit the monthly teaching for your town and congregation.',
        'zu' => 'Hlela imfundiso yenyanga yedolobha lakho nebandla.',
        'xh' => 'Hlela imfundiso yenyanga yedolophu kunye nebandla lakho.',
        'pt' => 'Edite o ensino mensal para sua cidade e congregação.'
    ],

    // ==================== AMPTES TAB ====================
    'no_access' => [
        'af' => 'Jy het nie toegang tot hierdie blad nie.',
        'en' => 'You do not have access to this page.',
        'zu' => 'Awunayo imvume yokungena kuleli khasi.',
        'xh' => 'Awunayo imvume yokungena kweli phepha.',
        'pt' => 'Você não tem acesso a esta página.'
    ],
    'search_offices' => [
        'af' => 'Soek ampte...',
        'en' => 'Search offices...',
        'zu' => 'Sesha izikhundla...',
        'xh' => 'Khangela izikhundla...',
        'pt' => 'Pesquisar cargos...'
    ],
    'no_office_holders' => [
        'af' => 'Geen ampte gevind nie.',
        'en' => 'No office holders found.',
        'zu' => 'Akukho zikhundla ezitholiwe.',
        'xh' => 'Akukho zikhundla zifunyenweyo.',
        'pt' => 'Nenhum titular de cargo encontrado.'
    ],
    'view_calendar' => [
        'af' => 'Kyk Kalender',
        'en' => 'View Calendar',
        'zu' => 'Buka Ikhalenda',
        'xh' => 'Jonga Ikhalenda',
        'pt' => 'Ver Calendário'
    ],
    'change_role' => [
        'af' => 'Verander Amp',
        'en' => 'Change Role',
        'zu' => 'Shintsha Isikhundla',
        'xh' => 'Tshintsha Isikhundla',
        'pt' => 'Alterar Cargo'
    ],
    'user' => [
        'af' => 'Gebruiker',
        'en' => 'User',
        'zu' => 'Umsebenzisi',
        'xh' => 'Umsebenzisi',
        'pt' => 'Usuário'
    ],
    'new_role' => [
        'af' => 'Nuwe Amp',
        'en' => 'New Role',
        'zu' => 'Isikhundla Esisha',
        'xh' => 'Isikhundla Esitsha',
        'pt' => 'Novo Cargo'
    ],
    'select_option' => [
        'af' => 'Kies...',
        'en' => 'Select...',
        'zu' => 'Khetha...',
        'xh' => 'Khetha...',
        'pt' => 'Selecionar...'
    ],

    // ==================== AFSPRAKE TAB ====================
    'pending_requests' => [
        'af' => 'Hangende Versoeke',
        'en' => 'Pending Requests',
        'zu' => 'Izicelo Ezilindile',
        'xh' => 'Izicelo Ezilindileyo',
        'pt' => 'Solicitações Pendentes'
    ],
    'wants_appointment' => [
        'af' => 'wil \'n afspraak maak',
        'en' => 'wants to make an appointment',
        'zu' => 'ufuna ukwenza isivumelwano',
        'xh' => 'ufuna ukwenza isivumelwano',
        'pt' => 'quer marcar um compromisso'
    ],
    'notes' => [
        'af' => 'Notas',
        'en' => 'Notes',
        'zu' => 'Amanothi',
        'xh' => 'Amanqaku',
        'pt' => 'Notas'
    ],
    'make_appointment' => [
        'af' => 'Maak Afspraak',
        'en' => 'Make Appointment',
        'zu' => 'Yenza Isivumelwano',
        'xh' => 'Yenza Isivumelwano',
        'pt' => 'Marcar Compromisso'
    ],
    'with' => [
        'af' => 'Met',
        'en' => 'With',
        'zu' => 'No',
        'xh' => 'No',
        'pt' => 'Com'
    ],
    'date' => [
        'af' => 'Datum',
        'en' => 'Date',
        'zu' => 'Usuku',
        'xh' => 'Umhla',
        'pt' => 'Data'
    ],
    'time' => [
        'af' => 'Tyd',
        'en' => 'Time',
        'zu' => 'Isikhathi',
        'xh' => 'Ixesha',
        'pt' => 'Hora'
    ],
    'location' => [
        'af' => 'Plek',
        'en' => 'Location',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local'
    ],
    'send_request' => [
        'af' => 'Stuur Versoek',
        'en' => 'Send Request',
        'zu' => 'Thumela Isicelo',
        'xh' => 'Thumela Isicelo',
        'pt' => 'Enviar Solicitação'
    ],

    // ==================== BIBLE ====================
    'bible_reader' => [
        'af' => 'Bybel Leser',
        'en' => 'Bible Reader',
        'zu' => 'Umfundi WeBhayibheli',
        'xh' => 'Umfundi WeBhayibhile',
        'pt' => 'Leitor da Bíblia'
    ],
    'quick_navigation' => [
        'af' => 'Vinnige Navigasie',
        'en' => 'Quick Navigation',
        'zu' => 'Ukuzulazula Okusheshayo',
        'xh' => 'Ukuhamba Okukhawulezileyo',
        'pt' => 'Navegação Rápida'
    ],
    'choose_testament' => [
        'af' => 'Kies Testament',
        'en' => 'Choose Testament',
        'zu' => 'Khetha Itestamente',
        'xh' => 'Khetha Itestamente',
        'pt' => 'Escolha Testamento'
    ],
    'old_testament' => [
        'af' => 'Ou Testament',
        'en' => 'Old Testament',
        'zu' => 'Itestamente Elidala',
        'xh' => 'Itestamente Endala',
        'pt' => 'Antigo Testamento'
    ],
    'new_testament' => [
        'af' => 'Nuwe Testament',
        'en' => 'New Testament',
        'zu' => 'Itestamente Elisha',
        'xh' => 'Itestamente Entsha',
        'pt' => 'Novo Testamento'
    ],
    'genesis_malachi' => [
        'af' => 'Genesis - Maleagi',
        'en' => 'Genesis - Malachi',
        'zu' => 'UGenesisi - UMalaki',
        'xh' => 'IGenesis - UMalaki',
        'pt' => 'Gênesis - Malaquias'
    ],
    'matthew_revelation' => [
        'af' => 'Matteus - Openbaring',
        'en' => 'Matthew - Revelation',
        'zu' => 'UMathewu - ISambulo',
        'xh' => 'UMateyu - ISityhilelo',
        'pt' => 'Mateus - Apocalipse'
    ],
    'back' => [
        'af' => 'Terug',
        'en' => 'Back',
        'zu' => 'Emuva',
        'xh' => 'Emva',
        'pt' => 'Voltar'
    ],
    'choose_book' => [
        'af' => 'Kies Boek',
        'en' => 'Choose Book',
        'zu' => 'Khetha Incwadi',
        'xh' => 'Khetha Incwadi',
        'pt' => 'Escolha Livro'
    ],
    'choose_chapter' => [
        'af' => 'Kies Hoofstuk',
        'en' => 'Choose Chapter',
        'zu' => 'Khetha Isahluko',
        'xh' => 'Khetha Isahluko',
        'pt' => 'Escolha Capítulo'
    ],
    'search_bible' => [
        'af' => 'Soek in die Bybel',
        'en' => 'Search the Bible',
        'zu' => 'Sesha IBhayibheli',
        'xh' => 'Khangela IBhayibhile',
        'pt' => 'Pesquisar na Bíblia'
    ],
    'type_search_term' => [
        'af' => 'Tik soekwoord...',
        'en' => 'Type search term...',
        'zu' => 'Thayipha igama lokusesha...',
        'xh' => 'Chwetheza igama lokukhangela...',
        'pt' => 'Digite termo de pesquisa...'
    ],
    'no_notes_yet' => [
        'af' => 'Geen notas nog nie. Klik op \'n vers om \'n nota by te voeg!',
        'en' => 'No notes yet. Click on a verse to add a note!',
        'zu' => 'Awukabi namanothi. Chofoza ivesi ukuze ungeze inothi!',
        'xh' => 'Akukho manqaku okwangoku. Cofa ivesi ukongeza inqaku!',
        'pt' => 'Nenhuma nota ainda. Clique em um versículo para adicionar uma nota!'
    ],
    'write_note_here' => [
        'af' => 'Skryf jou nota hier...',
        'en' => 'Write your note here...',
        'zu' => 'Bhala inothi lakho lapha...',
        'xh' => 'Bhala inqaku lakho apha...',
        'pt' => 'Escreva sua nota aqui...'
    ],
    'bookmarks' => [
        'af' => 'Boekmerke',
        'en' => 'Bookmarks',
        'zu' => 'Izimaki Zebhuku',
        'xh' => 'Izimaki Zeencwadi',
        'pt' => 'Favoritos'
    ],
    'no_bookmarks_yet' => [
        'af' => 'Geen boekmerke nog nie.',
        'en' => 'No bookmarks yet.',
        'zu' => 'Azikho izimaki zebhuku okwamanje.',
        'xh' => 'Akukho zimaki zeencwadi okwangoku.',
        'pt' => 'Nenhum favorito ainda.'
    ],
    'ai_commentary' => [
        'af' => 'AI Kommentaar',
        'en' => 'AI Commentary',
        'zu' => 'Ukuphawula kwe-AI',
        'xh' => 'Inkcazelo ye-AI',
        'pt' => 'Comentário IA'
    ],
    'select_verse_ai' => [
        'af' => 'Kies \'n vers en vra AI \'n vraag.',
        'en' => 'Select a verse and ask AI a question.',
        'zu' => 'Khetha ivesi bese ubuza i-AI umbuzo.',
        'xh' => 'Khetha ivesi uze ubuze i-AI umbuzo.',
        'pt' => 'Selecione um versículo e pergunte à IA.'
    ],
    'cross_references' => [
        'af' => 'Kruisverwysings',
        'en' => 'Cross References',
        'zu' => 'Izinkomba Ezihlanganisayo',
        'xh' => 'Izalathiso Ezidibeneyo',
        'pt' => 'Referências Cruzadas'
    ],
    'select_verse_cross_ref' => [
        'af' => 'Kies \'n vers om kruisverwysings te sien.',
        'en' => 'Select a verse to see cross-references.',
        'zu' => 'Khetha ivesi ukubona izinkomba ezihlanganisayo.',
        'xh' => 'Khetha ivesi ukubona izalathiso ezidibeneyo.',
        'pt' => 'Selecione um versículo para ver referências cruzadas.'
    ],
    'reading_plan' => [
        'af' => 'Leesplan',
        'en' => 'Reading Plan',
        'zu' => 'Uhlelo Lokufunda',
        'xh' => 'Isicwangciso Sokufunda',
        'pt' => 'Plano de Leitura'
    ],
    'choose_color' => [
        'af' => 'Kies Kleur',
        'en' => 'Choose Color',
        'zu' => 'Khetha Umbala',
        'xh' => 'Khetha Umbala',
        'pt' => 'Escolha Cor'
    ],
    'pink' => [
        'af' => 'Pienk',
        'en' => 'Pink',
        'zu' => 'Okupinki',
        'xh' => 'Ipinki',
        'pt' => 'Rosa'
    ],
    'orange' => [
        'af' => 'Oranje',
        'en' => 'Orange',
        'zu' => 'Orenji',
        'xh' => 'Iorenji',
        'pt' => 'Laranja'
    ],
    'yellow' => [
        'af' => 'Geel',
        'en' => 'Yellow',
        'zu' => 'Okuphuzi',
        'xh' => 'Omthubi',
        'pt' => 'Amarelo'
    ],
    'green' => [
        'af' => 'Groen',
        'en' => 'Green',
        'zu' => 'Oluhlaza',
        'xh' => 'Oluhlaza',
        'pt' => 'Verde'
    ],
    'blue' => [
        'af' => 'Blou',
        'en' => 'Blue',
        'zu' => 'Okuluhlaza Okwesibhakabhaka',
        'xh' => 'Ebhlowu',
        'pt' => 'Azul'
    ],
    'purple' => [
        'af' => 'Pers',
        'en' => 'Purple',
        'zu' => 'Okumfusa',
        'xh' => 'Okumfusa',
        'pt' => 'Roxo'
    ],
    'bookmark' => [
        'af' => 'Boekmerk',
        'en' => 'Bookmark',
        'zu' => 'Isimaki Sebhuku',
        'xh' => 'Isimaki Sencwadi',
        'pt' => 'Favorito'
    ],
    'add_note' => [
        'af' => 'Voeg nota by',
        'en' => 'Add note',
        'zu' => 'Engeza inothi',
        'xh' => 'Yongeza inqaku',
        'pt' => 'Adicionar nota'
    ],
    'ask_ai' => [
        'af' => 'Vra AI oor gedeelte',
        'en' => 'Ask AI about passage',
        'zu' => 'Buza i-AI ngendima',
        'xh' => 'Buza i-AI ngecandelo',
        'pt' => 'Perguntar à IA sobre passagem'
    ],
    'cross_refs' => [
        'af' => 'Kruisverwysings',
        'en' => 'Cross Refs',
        'zu' => 'Izinkomba',
        'xh' => 'Izalathiso',
        'pt' => 'Refs Cruzadas'
    ],
    'copy' => [
        'af' => 'Kopieer',
        'en' => 'Copy',
        'zu' => 'Kopisha',
        'xh' => 'Kopa',
        'pt' => 'Copiar'
    ],
    'share' => [
        'af' => 'Deel',
        'en' => 'Share',
        'zu' => 'Yabelana',
        'xh' => 'Yabelana',
        'pt' => 'Compartilhar'
    ],
    'navigate' => [
        'af' => 'Navigeer',
        'en' => 'Navigate',
        'zu' => 'Zulazula',
        'xh' => 'Hambisa',
        'pt' => 'Navegar'
    ],
    'decrease' => [
        'af' => 'Verklein',
        'en' => 'Decrease',
        'zu' => 'Nciphisa',
        'xh' => 'Nciphisa',
        'pt' => 'Diminuir'
    ],
    'increase' => [
        'af' => 'Vergroot',
        'en' => 'Increase',
        'zu' => 'Khulisa',
        'xh' => 'Yandisa',
        'pt' => 'Aumentar'
    ],
    'plan' => [
        'af' => 'Plan',
        'en' => 'Plan',
        'zu' => 'Uhlelo',
        'xh' => 'Isicwangciso',
        'pt' => 'Plano'
    ],
    'remove' => [
        'af' => 'Verwyder',
        'en' => 'Remove',
        'zu' => 'Susa',
        'xh' => 'Susa',
        'pt' => 'Remover'
    ],

    // ==================== CALENDAR ====================
    'ai_calendar' => [
        'af' => 'AI Kalender',
        'en' => 'AI Calendar',
        'zu' => 'Ikhalenda le-AI',
        'xh' => 'Ikhalenda ye-AI',
        'pt' => 'Calendário IA'
    ],
    'day' => [
        'af' => 'Dag',
        'en' => 'Day',
        'zu' => 'Usuku',
        'xh' => 'Usuku',
        'pt' => 'Dia'
    ],
    'week' => [
        'af' => 'Week',
        'en' => 'Week',
        'zu' => 'Iviki',
        'xh' => 'Iveki',
        'pt' => 'Semana'
    ],
    'today' => [
        'af' => 'Vandag',
        'en' => 'Today',
        'zu' => 'Namuhla',
        'xh' => 'Namhlanje',
        'pt' => 'Hoje'
    ],
    'sun' => [
        'af' => 'So',
        'en' => 'Sun',
        'zu' => 'Son',
        'xh' => 'ICa',
        'pt' => 'Dom'
    ],
    'mon' => [
        'af' => 'Ma',
        'en' => 'Mon',
        'zu' => 'Mso',
        'xh' => 'Mvu',
        'pt' => 'Seg'
    ],
    'tue' => [
        'af' => 'Di',
        'en' => 'Tue',
        'zu' => 'Bil',
        'xh' => 'Lwe',
        'pt' => 'Ter'
    ],
    'wed' => [
        'af' => 'Wo',
        'en' => 'Wed',
        'zu' => 'Tha',
        'xh' => 'Tha',
        'pt' => 'Qua'
    ],
    'thu' => [
        'af' => 'Do',
        'en' => 'Thu',
        'zu' => 'Sin',
        'xh' => 'Sin',
        'pt' => 'Qui'
    ],
    'fri' => [
        'af' => 'Vr',
        'en' => 'Fri',
        'zu' => 'Hla',
        'xh' => 'Hla',
        'pt' => 'Sex'
    ],
    'sat' => [
        'af' => 'Sa',
        'en' => 'Sat',
        'zu' => 'Mgq',
        'xh' => 'Mgq',
        'pt' => 'Sáb'
    ],
    // Full day names for calendar
    'sunday' => [
        'af' => 'Sondag',
        'en' => 'Sunday',
        'zu' => 'ISonto',
        'xh' => 'ICawa',
        'pt' => 'Domingo'
    ],
    'monday' => [
        'af' => 'Maandag',
        'en' => 'Monday',
        'zu' => 'UMsombuluko',
        'xh' => 'UMvulo',
        'pt' => 'Segunda-feira'
    ],
    'tuesday' => [
        'af' => 'Dinsdag',
        'en' => 'Tuesday',
        'zu' => 'ULwesibili',
        'xh' => 'ULwesibini',
        'pt' => 'Terça-feira'
    ],
    'wednesday' => [
        'af' => 'Woensdag',
        'en' => 'Wednesday',
        'zu' => 'ULwesithathu',
        'xh' => 'ULwesithathu',
        'pt' => 'Quarta-feira'
    ],
    'thursday' => [
        'af' => 'Donderdag',
        'en' => 'Thursday',
        'zu' => 'ULwesine',
        'xh' => 'ULwesine',
        'pt' => 'Quinta-feira'
    ],
    'friday' => [
        'af' => 'Vrydag',
        'en' => 'Friday',
        'zu' => 'ULwesihlanu',
        'xh' => 'ULwesihlanu',
        'pt' => 'Sexta-feira'
    ],
    'saturday' => [
        'af' => 'Saterdag',
        'en' => 'Saturday',
        'zu' => 'UMgqibelo',
        'xh' => 'UMgqibelo',
        'pt' => 'Sábado'
    ],
    // Month names for calendar
    'january' => [
        'af' => 'Januarie',
        'en' => 'January',
        'zu' => 'UMasingana',
        'xh' => 'UJanyuwari',
        'pt' => 'Janeiro'
    ],
    'february' => [
        'af' => 'Februarie',
        'en' => 'February',
        'zu' => 'UNhlolanja',
        'xh' => 'UFebruwari',
        'pt' => 'Fevereiro'
    ],
    'march' => [
        'af' => 'Maart',
        'en' => 'March',
        'zu' => 'UNdasa',
        'xh' => 'UMatshi',
        'pt' => 'Março'
    ],
    'april' => [
        'af' => 'April',
        'en' => 'April',
        'zu' => 'UMbasa',
        'xh' => 'UEpreli',
        'pt' => 'Abril'
    ],
    'may' => [
        'af' => 'Mei',
        'en' => 'May',
        'zu' => 'UNhlaba',
        'xh' => 'UMeyi',
        'pt' => 'Maio'
    ],
    'june' => [
        'af' => 'Junie',
        'en' => 'June',
        'zu' => 'UNhlangulana',
        'xh' => 'UJuni',
        'pt' => 'Junho'
    ],
    'july' => [
        'af' => 'Julie',
        'en' => 'July',
        'zu' => 'UNtulikazi',
        'xh' => 'UJulayi',
        'pt' => 'Julho'
    ],
    'august' => [
        'af' => 'Augustus',
        'en' => 'August',
        'zu' => 'UNcwaba',
        'xh' => 'UAgasti',
        'pt' => 'Agosto'
    ],
    'september' => [
        'af' => 'September',
        'en' => 'September',
        'zu' => 'UMandulo',
        'xh' => 'USeptemba',
        'pt' => 'Setembro'
    ],
    'october' => [
        'af' => 'Oktober',
        'en' => 'October',
        'zu' => 'UMfumfu',
        'xh' => 'UOktobha',
        'pt' => 'Outubro'
    ],
    'november' => [
        'af' => 'November',
        'en' => 'November',
        'zu' => 'ULwezi',
        'xh' => 'UNovemba',
        'pt' => 'Novembro'
    ],
    'december' => [
        'af' => 'Desember',
        'en' => 'December',
        'zu' => 'UZibandlela',
        'xh' => 'UDisemba',
        'pt' => 'Dezembro'
    ],
    // Singular appointment
    'appointment' => [
        'af' => 'Afspraak',
        'en' => 'Appointment',
        'zu' => 'Isivumelwano',
        'xh' => 'Isivumelwano',
        'pt' => 'Compromisso'
    ],
    'create_new' => [
        'af' => 'Skep Nuwe',
        'en' => 'Create New',
        'zu' => 'Dala Okusha',
        'xh' => 'Yenza Okutsha',
        'pt' => 'Criar Novo'
    ],
    'event' => [
        'af' => 'Aktiwiteit',
        'en' => 'Activity',
        'zu' => 'Umsebenzi',
        'xh' => 'Umsebenzi',
        'pt' => 'Atividade'
    ],
    'title' => [
        'af' => 'Titel',
        'en' => 'Title',
        'zu' => 'Isihloko',
        'xh' => 'Isihloko',
        'pt' => 'Título'
    ],
    'room' => [
        'af' => 'Kamer',
        'en' => 'Room',
        'zu' => 'Igumbi',
        'xh' => 'Igumbi',
        'pt' => 'Sala'
    ],
    'select_room' => [
        'af' => 'Kies kamer...',
        'en' => 'Select room...',
        'zu' => 'Khetha igumbi...',
        'xh' => 'Khetha igumbi...',
        'pt' => 'Selecionar sala...'
    ],
    'with_whom' => [
        'af' => 'Met wie?',
        'en' => 'With whom?',
        'zu' => 'Nobani?',
        'xh' => 'Nabani?',
        'pt' => 'Com quem?'
    ],
    'select_user' => [
        'af' => 'Kies gebruiker...',
        'en' => 'Select user...',
        'zu' => 'Khetha umsebenzisi...',
        'xh' => 'Khetha umsebenzisi...',
        'pt' => 'Selecionar usuário...'
    ],
    'type_name' => [
        'af' => 'Tik naam in...',
        'en' => 'Type name...',
        'zu' => 'Thayipha igama...',
        'xh' => 'Chwetheza igama...',
        'pt' => 'Digite nome...'
    ],
    'share_with_spouse' => [
        'af' => 'Deel met gade',
        'en' => 'Share with spouse',
        'zu' => 'Yabelana nomlingani',
        'xh' => 'Yabelana nomlingani',
        'pt' => 'Compartilhar com cônjuge'
    ],
    'no_events' => [
        'af' => 'Geen aktiwiteite',
        'en' => 'No activities',
        'zu' => 'Akukho misebenzi',
        'xh' => 'Akukho misebenzi',
        'pt' => 'Sem atividades'
    ],
    'confirm_delete' => [
        'af' => 'Is jy seker jy wil hierdie inskrywing verwyder?',
        'en' => 'Are you sure you want to delete this entry?',
        'zu' => 'Uqinisekile ukuthi ufuna ukususa lokhu okufakiwe?',
        'xh' => 'Uqinisekile ukuba ufuna ukucima oku okufakiweyo?',
        'pt' => 'Tem certeza de que deseja excluir esta entrada?'
    ],
    'saved_success' => [
        'af' => 'Suksesvol gestoor!',
        'en' => 'Successfully saved!',
        'zu' => 'Kugcinwe ngempumelelo!',
        'xh' => 'Igcinwe ngempumelelo!',
        'pt' => 'Salvo com sucesso!'
    ],
    'deleted_success' => [
        'af' => 'Suksesvol verwyder!',
        'en' => 'Successfully deleted!',
        'zu' => 'Kususiwe ngempumelelo!',
        'xh' => 'Icinyiwe ngempumelelo!',
        'pt' => 'Excluído com sucesso!'
    ],
    'see_you_at' => [
        'af' => 'Sien julle',
        'en' => 'See you',
        'zu' => 'Sizobonana',
        'xh' => 'Sobonana',
        'pt' => 'Vejo vocês'
    ],
    'type' => [
        'af' => 'Tipe',
        'en' => 'Type',
        'zu' => 'Uhlobo',
        'xh' => 'Uhlobo',
        'pt' => 'Tipo'
    ],

    // ==================== DIARY ====================
    'ai_diary' => [
        'af' => 'AI Dagboek',
        'en' => 'AI Diary',
        'zu' => 'Idayari ye-AI',
        'xh' => 'Idayari ye-AI',
        'pt' => 'Diário IA'
    ],
    'my_diary' => [
        'af' => 'My Dagboek',
        'en' => 'My Diary',
        'zu' => 'Idayari Yami',
        'xh' => 'Idayari Yam',
        'pt' => 'Meu Diário'
    ],
    'preserve_thoughts' => [
        'af' => 'Bewaar jou gedagtes, drome en gebede',
        'en' => 'Preserve your thoughts, dreams and prayers',
        'zu' => 'Gcina imicabango yakho, amaphupho nemithandazo',
        'xh' => 'Gcina iingcinga zakho, amaphupha nemithandazo',
        'pt' => 'Preserve seus pensamentos, sonhos e orações'
    ],
    'total_entries' => [
        'af' => 'Totale Inskrywings',
        'en' => 'Total Entries',
        'zu' => 'Okufakiwe Kuphelele',
        'xh' => 'Okufakiweyo Kupheleleyo',
        'pt' => 'Total de Entradas'
    ],
    'this_month' => [
        'af' => 'Hierdie Maand',
        'en' => 'This Month',
        'zu' => 'Lenyanga',
        'xh' => 'Lenyanga',
        'pt' => 'Este Mês'
    ],
    'day_streak' => [
        'af' => 'Dag Reeks',
        'en' => 'Day Streak',
        'zu' => 'Ukulandelana Kwezinsuku',
        'xh' => 'Ukulandelana Kweentsuku',
        'pt' => 'Sequência de Dias'
    ],
    'total_words' => [
        'af' => 'Totale Woorde',
        'en' => 'Total Words',
        'zu' => 'Amagama Aphelele',
        'xh' => 'Amagama Apheleleyo',
        'pt' => 'Total de Palavras'
    ],
    'timeline' => [
        'af' => 'Tydlyn',
        'en' => 'Timeline',
        'zu' => 'Uhlelo Lwesikhathi',
        'xh' => 'Uluhlu Lwexesha',
        'pt' => 'Linha do Tempo'
    ],
    'gallery' => [
        'af' => 'Galery',
        'en' => 'Gallery',
        'zu' => 'Igalari',
        'xh' => 'Igalari',
        'pt' => 'Galeria'
    ],
    'new_entry' => [
        'af' => 'Nuwe Inskrywing',
        'en' => 'New Entry',
        'zu' => 'Okufakiwe Okusha',
        'xh' => 'Okufakiweyo Okutsha',
        'pt' => 'Nova Entrada'
    ],
    'search_entries' => [
        'af' => 'Soek inskrywings...',
        'en' => 'Search entries...',
        'zu' => 'Sesha okufakiwe...',
        'xh' => 'Khangela okufakiweyo...',
        'pt' => 'Pesquisar entradas...'
    ],
    'newest_first' => [
        'af' => 'Nuutste Eerste',
        'en' => 'Newest First',
        'zu' => 'Okusha Kuqala',
        'xh' => 'Okutsha Kuqala',
        'pt' => 'Mais Recente Primeiro'
    ],
    'oldest_first' => [
        'af' => 'Oudste Eerste',
        'en' => 'Oldest First',
        'zu' => 'Okudala Kuqala',
        'xh' => 'Okudala Kuqala',
        'pt' => 'Mais Antigo Primeiro'
    ],
    'title_az' => [
        'af' => 'Titel A-Z',
        'en' => 'Title A-Z',
        'zu' => 'Isihloko A-Z',
        'xh' => 'Isihloko A-Z',
        'pt' => 'Título A-Z'
    ],
    'all' => [
        'af' => 'Alle',
        'en' => 'All',
        'zu' => 'Konke',
        'xh' => 'Konke',
        'pt' => 'Todos'
    ],
    'this_week' => [
        'af' => 'Hierdie Week',
        'en' => 'This Week',
        'zu' => 'Leliviki',
        'xh' => 'Leveki',
        'pt' => 'Esta Semana'
    ],
    'this_year' => [
        'af' => 'Hierdie Jaar',
        'en' => 'This Year',
        'zu' => 'Lonyaka',
        'xh' => 'Lonyaka',
        'pt' => 'Este Ano'
    ],
    'loading_entries' => [
        'af' => 'Laai inskrywings...',
        'en' => 'Loading entries...',
        'zu' => 'Ilayisha okufakiwe...',
        'xh' => 'Ilayisha okufakiweyo...',
        'pt' => 'Carregando entradas...'
    ],
    'loading_gallery' => [
        'af' => 'Laai galery...',
        'en' => 'Loading gallery...',
        'zu' => 'Ilayisha igalari...',
        'xh' => 'Ilayisha igalari...',
        'pt' => 'Carregando galeria...'
    ],
    'search_through_all' => [
        'af' => 'Soek deur al jou inskrywings...',
        'en' => 'Search through all your entries...',
        'zu' => 'Sesha kuyo yonke okufakiwe kwakho...',
        'xh' => 'Khangela kuyo yonke okufakiweyo kwakho...',
        'pt' => 'Pesquisar em todas as suas entradas...'
    ],
    'titles' => [
        'af' => 'Titels',
        'en' => 'Titles',
        'zu' => 'Izihloko',
        'xh' => 'Izihloko',
        'pt' => 'Títulos'
    ],
    'content' => [
        'af' => 'Inhoud',
        'en' => 'Content',
        'zu' => 'Okuqukethwe',
        'xh' => 'Okuqukethweyo',
        'pt' => 'Conteúdo'
    ],
    'tags' => [
        'af' => 'Etikette',
        'en' => 'Tags',
        'zu' => 'Amalebula',
        'xh' => 'Iilebhuli',
        'pt' => 'Etiquetas'
    ],
    'start_typing_search' => [
        'af' => 'Begin tik om te soek...',
        'en' => 'Start typing to search...',
        'zu' => 'Qala ukuthayipha ukusesha...',
        'xh' => 'Qala ukuchwetheza ukukhangela...',
        'pt' => 'Comece a digitar para pesquisar...'
    ],
    'date_time' => [
        'af' => 'Datum & Tyd',
        'en' => 'Date & Time',
        'zu' => 'Usuku & Isikhathi',
        'xh' => 'Umhla & Ixesha',
        'pt' => 'Data e Hora'
    ],
    'my_thoughts_today' => [
        'af' => 'My gedagtes vir vandag...',
        'en' => 'My thoughts for today...',
        'zu' => 'Imicabango yami yanamuhla...',
        'xh' => 'Iingcinga zam zanamhlanje...',
        'pt' => 'Meus pensamentos para hoje...'
    ],
    'write_thoughts_here' => [
        'af' => 'Skryf jou gedagtes hier...',
        'en' => 'Write your thoughts here...',
        'zu' => 'Bhala imicabango yakho lapha...',
        'xh' => 'Bhala iingcinga zakho apha...',
        'pt' => 'Escreva seus pensamentos aqui...'
    ],
    'type_tag_enter' => [
        'af' => 'Tik etiket en druk Enter...',
        'en' => 'Type tag and press Enter...',
        'zu' => 'Thayipha ilebula bese ucindezela u-Enter...',
        'xh' => 'Chwetheza ilebhuli uze ucinezele u-Enter...',
        'pt' => 'Digite etiqueta e pressione Enter...'
    ],
    'mood' => [
        'af' => 'Gemoed',
        'en' => 'Mood',
        'zu' => 'Umuzwa',
        'xh' => 'Imvakalelo',
        'pt' => 'Humor'
    ],
    'weather' => [
        'af' => 'Weer',
        'en' => 'Weather',
        'zu' => 'Isimo Sezulu',
        'xh' => 'Imozulu',
        'pt' => 'Clima'
    ],
    'reminder' => [
        'af' => 'Herinnering',
        'en' => 'Reminder',
        'zu' => 'Isikhumbuzo',
        'xh' => 'Isikhumbuzo',
        'pt' => 'Lembrete'
    ],
    'no_reminder' => [
        'af' => 'Geen herinnering',
        'en' => 'No reminder',
        'zu' => 'Akukho sikhumbuzo',
        'xh' => 'Akukho sikhumbuzo',
        'pt' => 'Sem lembrete'
    ],
    'minutes_before' => [
        'af' => 'minute voor',
        'en' => 'minutes before',
        'zu' => 'imizuzu ngaphambi',
        'xh' => 'imizuzu phambi',
        'pt' => 'minutos antes'
    ],
    'hour_before' => [
        'af' => 'uur voor',
        'en' => 'hour before',
        'zu' => 'ihora ngaphambi',
        'xh' => 'iyure phambi',
        'pt' => 'hora antes'
    ],
    'hours_before' => [
        'af' => 'ure voor',
        'en' => 'hours before',
        'zu' => 'amahora ngaphambi',
        'xh' => 'iiyure phambi',
        'pt' => 'horas antes'
    ],
    'day_before' => [
        'af' => 'dag voor',
        'en' => 'day before',
        'zu' => 'usuku ngaphambi',
        'xh' => 'usuku phambi',
        'pt' => 'dia antes'
    ],
    'add_to_calendar' => [
        'af' => 'Voeg by kalender',
        'en' => 'Add to calendar',
        'zu' => 'Engeza kukhalenda',
        'xh' => 'Yongeza kwikhalenda',
        'pt' => 'Adicionar ao calendário'
    ],
    'ai_assist' => [
        'af' => 'AI Hulp',
        'en' => 'AI Assist',
        'zu' => 'Usizo lwe-AI',
        'xh' => 'Uncedo lwe-AI',
        'pt' => 'Assistência IA'
    ],
    'share_entry' => [
        'af' => 'Deel Inskrywing',
        'en' => 'Share Entry',
        'zu' => 'Yabelana Ngokufakiwe',
        'xh' => 'Yabelana Ngokufakiweyo',
        'pt' => 'Compartilhar Entrada'
    ],
    'share_with_friend' => [
        'af' => 'Deel met Vriend',
        'en' => 'Share with Friend',
        'zu' => 'Yabelana Nomngani',
        'xh' => 'Yabelana Nomhlobo',
        'pt' => 'Compartilhar com Amigo'
    ],
    'copy_link' => [
        'af' => 'Kopieer Skakel',
        'en' => 'Copy Link',
        'zu' => 'Kopisha Isixhumanisi',
        'xh' => 'Kopa Ikhonkco',
        'pt' => 'Copiar Link'
    ],
    'export_pdf' => [
        'af' => 'Voer Uit as PDF',
        'en' => 'Export as PDF',
        'zu' => 'Thumela nge-PDF',
        'xh' => 'Thumela nge-PDF',
        'pt' => 'Exportar como PDF'
    ],
    'words' => [
        'af' => 'woorde',
        'en' => 'words',
        'zu' => 'amagama',
        'xh' => 'amagama',
        'pt' => 'palavras'
    ],

    // ==================== PRAYERS ====================
    'prayers_testimonies' => [
        'af' => 'Gebede & Getuienisse',
        'en' => 'Prayers & Testimonies',
        'zu' => 'Imithandazo & Ubufakazi',
        'xh' => 'Imithandazo & Ubungqina',
        'pt' => 'Orações e Testemunhos'
    ],
    'share_heart_pray' => [
        'af' => 'Deel jou hart, bid saam, vier saam',
        'en' => 'Share your heart, pray together, celebrate together',
        'zu' => 'Yabelana ngenhliziyo yakho, thandaza ndawonye, gubha ndawonye',
        'xh' => 'Yabelana ngentliziyo yakho, thandaza kunye, bhiyozela kunye',
        'pt' => 'Compartilhe seu coração, ore junto, celebre junto'
    ],
    'share_prayer_testimony' => [
        'af' => 'Deel jou gebedsnood of getuienis',
        'en' => 'Share your prayer need or testimony',
        'zu' => 'Yabelana ngesidingo sakho somthandazo noma ubufakazi',
        'xh' => 'Yabelana ngesidingo sakho somthandazo okanye ubungqina',
        'pt' => 'Compartilhe sua necessidade de oração ou testemunho'
    ],
    'prayer' => [
        'af' => 'Gebed',
        'en' => 'Prayer',
        'zu' => 'Umthandazo',
        'xh' => 'Umthandazo',
        'pt' => 'Oração'
    ],
    'testimony' => [
        'af' => 'Getuienis',
        'en' => 'Testimony',
        'zu' => 'Ubufakazi',
        'xh' => 'Ubungqina',
        'pt' => 'Testemunho'
    ],
    'share_heart_here' => [
        'af' => 'Deel jou hart hier...',
        'en' => 'Share your heart here...',
        'zu' => 'Yabelana ngenhliziyo yakho lapha...',
        'xh' => 'Yabelana ngentliziyo yakho apha...',
        'pt' => 'Compartilhe seu coração aqui...'
    ],
    'choose_photo' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (uyazikhethela)',
        'xh' => 'Khetha umfanekiso (uyazikhethela)',
        'pt' => 'Escolher foto (opcional)'
    ],
    'loading_prayers' => [
        'af' => 'Laai gebede...',
        'en' => 'Loading prayers...',
        'zu' => 'Ilayisha imithandazo...',
        'xh' => 'Ilayisha imithandazo...',
        'pt' => 'Carregando orações...'
    ],
    'comments' => [
        'af' => 'Kommentaar',
        'en' => 'Comments',
        'zu' => 'Amazwana',
        'xh' => 'Amagqabaza',
        'pt' => 'Comentários'
    ],
    'write_encouraging' => [
        'af' => 'Skryf \'n bemoedigende woord...',
        'en' => 'Write an encouraging word...',
        'zu' => 'Bhala igama elikhuthazayo...',
        'xh' => 'Bhala ilizwi elikhuthazayo...',
        'pt' => 'Escreva uma palavra de encorajamento...'
    ],
    'send' => [
        'af' => 'Stuur',
        'en' => 'Send',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Enviar'
    ],

    // ==================== PRAYERS EXTRA ====================
    'prayers_subtitle' => [
        'af' => 'Deel jou hart, bid saam, vier saam',
        'en' => 'Share your heart, pray together, celebrate together',
        'zu' => 'Yabelana ngenhliziyo yakho, thandaza ndawonye, gubha ndawonye',
        'xh' => 'Yabelana ngentliziyo yakho, thandaza kunye, bhiyozela kunye',
        'pt' => 'Compartilhe seu coração, ore junto, celebre junto'
    ],
    'share_heart_placeholder' => [
        'af' => 'Deel jou hart hier...',
        'en' => 'Share your heart here...',
        'zu' => 'Yabelana ngenhliziyo yakho lapha...',
        'xh' => 'Yabelana ngentliziyo yakho apha...',
        'pt' => 'Compartilhe seu coração aqui...'
    ],
    'choose_photo_optional' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (uyazikhethela)',
        'xh' => 'Khetha umfanekiso (uyazikhethela)',
        'pt' => 'Escolher foto (opcional)'
    ],
    'write_encouraging_word' => [
        'af' => 'Skryf \'n bemoedigende woord...',
        'en' => 'Write an encouraging word...',
        'zu' => 'Bhala igama elikhuthazayo...',
        'xh' => 'Bhala ilizwi elikhuthazayo...',
        'pt' => 'Escreva uma palavra de encorajamento...'
    ],

    // ==================== GOSPEL MEDIA ====================
    'no_rooms_available' => [
        'af' => 'Geen kamers beskikbaar nie. Kontak admin.',
        'en' => 'No rooms available. Contact admin.',
        'zu' => 'Akukho magumbi atholakalayo. Xhumana no-admin.',
        'xh' => 'Akukho magumbi afumanekayo. Qhagamshelana no-admin.',
        'pt' => 'Nenhuma sala disponível. Contate o administrador.'
    ],
    'no_room_access' => [
        'af' => 'Jy het nie toegang tot hierdie kamer nie.',
        'en' => 'You do not have access to this room.',
        'zu' => 'Awunayo imvume yokungena kuleli gumbi.',
        'xh' => 'Awunayo imvume yokungena kweli gumbi.',
        'pt' => 'Você não tem acesso a esta sala.'
    ],
    'rooms' => [
        'af' => 'Kamers',
        'en' => 'Rooms',
        'zu' => 'Amagumbi',
        'xh' => 'Amagumbi',
        'pt' => 'Salas'
    ],
    'new_post' => [
        'af' => 'Nuwe Plasing',
        'en' => 'New Post',
        'zu' => 'Ukuposa Okusha',
        'xh' => 'Ukuposa Okutsha',
        'pt' => 'Nova Postagem'
    ],
    'post' => [
        'af' => 'Plaas',
        'en' => 'Post',
        'zu' => 'Posa',
        'xh' => 'Posa',
        'pt' => 'Postar'
    ],
    'what_share' => [
        'af' => 'Wat wil jy deel?',
        'en' => 'What do you want to share?',
        'zu' => 'Ufuna ukwabelana ngani?',
        'xh' => 'Ufuna ukwabelana ngantoni?',
        'pt' => 'O que você quer compartilhar?'
    ],
    'date_time_short' => [
        'af' => 'Datum/Tyd',
        'en' => 'Date/Time',
        'zu' => 'Usuku/Isikhathi',
        'xh' => 'Umhla/Ixesha',
        'pt' => 'Data/Hora'
    ],
    'place' => [
        'af' => 'Plek',
        'en' => 'Place',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local'
    ],
    'photo' => [
        'af' => 'Foto',
        'en' => 'Photo',
        'zu' => 'Isithombe',
        'xh' => 'Umfanekiso',
        'pt' => 'Foto'
    ],
    'no_posting_rights' => [
        'af' => 'Jy het nie plaas-regte in hierdie kamer nie.',
        'en' => 'You do not have posting rights in this room.',
        'zu' => 'Awunayo ilungelo lokuposa kuleli gumbi.',
        'xh' => 'Awunayo ilungelo lokuposa kweli gumbi.',
        'pt' => 'Você não tem direitos de postagem nesta sala.'
    ],
    'posts' => [
        'af' => 'Plasings',
        'en' => 'Posts',
        'zu' => 'Iziposa',
        'xh' => 'Iziposa',
        'pt' => 'Postagens'
    ],
    'loading_posts' => [
        'af' => 'Laai plasings...',
        'en' => 'Loading posts...',
        'zu' => 'Ilayisha iziposa...',
        'xh' => 'Ilayisha iziposa...',
        'pt' => 'Carregando postagens...'
    ],

    // ==================== ROOMS PAGE ====================
    'your_rooms' => [
        'af' => 'Jou Kamers',
        'en' => 'Your Rooms',
        'zu' => 'Amagumbi Akho',
        'xh' => 'Amagumbi Akho',
        'pt' => 'Suas Salas'
    ],
    'welcome' => [
        'af' => 'Welkom',
        'en' => 'Welcome',
        'zu' => 'Siyakwemukela',
        'xh' => 'Wamkelekile',
        'pt' => 'Bem-vindo'
    ],
    'town' => [
        'af' => 'Dorp',
        'en' => 'Town',
        'zu' => 'Idolobha',
        'xh' => 'Idolophu',
        'pt' => 'Cidade'
    ],
    'age' => [
        'af' => 'Ouderdom',
        'en' => 'Age',
        'zu' => 'Iminyaka',
        'xh' => 'Iminyaka',
        'pt' => 'Idade'
    ],
    'years' => [
        'af' => 'jaar',
        'en' => 'years',
        'zu' => 'iminyaka',
        'xh' => 'iminyaka',
        'pt' => 'anos'
    ],
    'amp_level' => [
        'af' => 'Amp Vlak',
        'en' => 'Amp Level',
        'zu' => 'Izinga Le-Amp',
        'xh' => 'Izinga Le-Amp',
        'pt' => 'Nível do Cargo'
    ],
    'apostle_desc' => [
        'af' => 'Apostel (Kan slegs opsienerskappe kies)',
        'en' => 'Apostle (Can only choose oversights)',
        'zu' => 'Umphostoli (Angakhetha ukuqashwa kuphela)',
        'xh' => 'Umpostile (Angakhetha ukongamela kuphela)',
        'pt' => 'Apóstolo (Pode apenas escolher supervisões)'
    ],
    'amp_2_6_desc' => [
        'af' => 'Amp 2-6 (Kan gemeentes & jeug in eie dorp kies)',
        'en' => 'Amp 2-6 (Can choose congregations & youth in own town)',
        'zu' => 'Amp 2-6 (Angakhetha amabandla nentsha edolobheni lakhe)',
        'xh' => 'Amp 2-6 (Angakhetha amabandla nolutsha edolophini yakhe)',
        'pt' => 'Cargo 2-6 (Pode escolher congregações e jovens na própria cidade)'
    ],
    'amp_8_desc' => [
        'af' => 'Amp 8+ (Kan slegs gemeente & opsienerskap sien, behalwe as bygevoeg)',
        'en' => 'Amp 8+ (Can only see congregation & oversight, unless added)',
        'zu' => 'Amp 8+ (Angabona ibandla nokuqashwa kuphela, ngaphandle uma wengezwe)',
        'xh' => 'Amp 8+ (Angabona ibandla nokongamela kuphela, ngaphandle kokuba wongezwe)',
        'pt' => 'Cargo 8+ (Pode ver apenas congregação e supervisão, a menos que adicionado)'
    ],
    'amp_7_desc' => [
        'af' => 'Amp 7 (Kan jeug/sondagskool in eie dorp kies)',
        'en' => 'Amp 7 (Can choose youth/sunday school in own town)',
        'zu' => 'Amp 7 (Angakhetha intsha/isikole sesonto edolobheni lakhe)',
        'xh' => 'Amp 7+ (Angakhetha ulutsha/isikolo sangesonto edolophini yakhe)',
        'pt' => 'Cargo 7 (Pode escolher jovens/escola dominical na própria cidade)'
    ],
    'automatic' => [
        'af' => 'Outomaties',
        'en' => 'Automatic',
        'zu' => 'Okuzenzakalelayo',
        'xh' => 'Okuzenzakalelayo',
        'pt' => 'Automático'
    ],
    'automatic_membership' => [
        'af' => 'Outomatiese lidmaatskap',
        'en' => 'Automatic membership',
        'zu' => 'Ubulungu obuzenzakalelayo',
        'xh' => 'Ubulungu obuzenzakalelayo',
        'pt' => 'Associação automática'
    ],
    'officer_can_choose' => [
        'af' => 'As offisier kan jy \'n ander gemeente kies',
        'en' => 'As officer you can choose another congregation',
        'zu' => 'Njengesikhulu ungakhetha elinye ibandla',
        'xh' => 'Njengegosa ungakhetha elinye ibandla',
        'pt' => 'Como oficial você pode escolher outra congregação'
    ],
    'auto_under_16' => [
        'af' => 'Outomaties (onder 16 jaar)',
        'en' => 'Automatic (under 16 years)',
        'zu' => 'Okuzenzakalelayo (ngaphansi kweminyaka engu-16)',
        'xh' => 'Okuzenzakalelayo (ngaphantsi kweminyaka eli-16)',
        'pt' => 'Automático (menores de 16 anos)'
    ],
    'auto_16_25' => [
        'af' => 'Outomaties (16-25 jaar, ongetroud)',
        'en' => 'Automatic (16-25 years, unmarried)',
        'zu' => 'Okuzenzakalelayo (iminyaka engu-16-25, ongashadile)',
        'xh' => 'Okuzenzakalelayo (iminyaka eli-16-25, ongatshatanga)',
        'pt' => 'Automático (16-25 anos, solteiro)'
    ],
    'auto_short' => [
        'af' => 'Outo',
        'en' => 'Auto',
        'zu' => 'Okhozenzakalela',
        'xh' => 'Okhozenzakalela',
        'pt' => 'Auto'
    ],
    'leave' => [
        'af' => 'Los',
        'en' => 'Leave',
        'zu' => 'Shiya',
        'xh' => 'Shiya',
        'pt' => 'Sair'
    ],
    'view' => [
        'af' => 'Bekyk',
        'en' => 'View',
        'zu' => 'Buka',
        'xh' => 'Jonga',
        'pt' => 'Ver'
    ],
    'joined' => [
        'af' => 'Gekose',
        'en' => 'Joined',
        'zu' => 'Ujoyine',
        'xh' => 'Uzibandakanye',
        'pt' => 'Associado'
    ],
    'available' => [
        'af' => 'Beskikbaar',
        'en' => 'Available',
        'zu' => 'Iyatholakala',
        'xh' => 'Iyafumaneka',
        'pt' => 'Disponível'
    ],
    'join' => [
        'af' => 'Sluit aan',
        'en' => 'Join',
        'zu' => 'Joyina',
        'xh' => 'Joyina',
        'pt' => 'Entrar'
    ],
    'no_rooms' => [
        'af' => 'Geen kamers beskikbaar nie.',
        'en' => 'No rooms available.',
        'zu' => 'Akukho magumbi atholakalayo.',
        'xh' => 'Akukho magumbi afumanekayo.',
        'pt' => 'Nenhuma sala disponível.'
    ],
    'back_to_media' => [
        'af' => 'Terug na Media',
        'en' => 'Back to Media',
        'zu' => 'Buyela ku-Media',
        'xh' => 'Buyela ku-Media',
        'pt' => 'Voltar para Mídia'
    ],
    'confirm_leave_congregation' => [
        'af' => 'Is jy seker jy wil hierdie gemeente los? Jy sal \'n ander moet kies.',
        'en' => 'Are you sure you want to leave this congregation? You will need to choose another.',
        'zu' => 'Uqinisekile ukuthi ufuna ukushiya leli bandla? Uzodinga ukukhetha elinye.',
        'xh' => 'Uqinisekile ukuba ufuna ukushiya eli bandla? Kuya kufuneka ukhethe elinye.',
        'pt' => 'Tem certeza de que deseja sair desta congregação? Você precisará escolher outra.'
    ],
    'confirm_leave_room' => [
        'af' => 'Is jy seker jy wil los',
        'en' => 'Are you sure you want to leave',
        'zu' => 'Uqinisekile ukuthi ufuna ukushiya',
        'xh' => 'Uqinisekile ukuba ufuna ukushiya',
        'pt' => 'Tem certeza de que deseja sair'
    ],
    'successfully_joined' => [
        'af' => 'Suksesvol aangesluit!',
        'en' => 'Successfully joined!',
        'zu' => 'Ujoyine ngempumelelo!',
        'xh' => 'Uzibandakanye ngempumelelo!',
        'pt' => 'Associado com sucesso!'
    ],
    'successfully_left' => [
        'af' => 'Suksesvol gelos!',
        'en' => 'Successfully left!',
        'zu' => 'Ushiye ngempumelelo!',
        'xh' => 'Ushiye ngempumelelo!',
        'pt' => 'Saiu com sucesso!'
    ],
    'cannot_leave_oversight' => [
        'af' => 'Jy kan nie jou opsienerskap los nie',
        'en' => 'You cannot leave your oversight',
        'zu' => 'Awukwazi ukushiya ukuqashwa kwakho',
        'xh' => 'Awukwazi ukushiya ukongamela kwakho',
        'pt' => 'Você não pode sair da sua supervisão'
    ],
    'cannot_leave_congregation' => [
        'af' => 'Jy kan nie jou gemeente los nie',
        'en' => 'You cannot leave your congregation',
        'zu' => 'Awukwazi ukushiya ibandla lakho',
        'xh' => 'Awukwazi ukushiya ibandla lakho',
        'pt' => 'Você não pode sair da sua congregação'
    ],

    // ==================== PROFILE PAGE ====================
    'export' => [
        'af' => 'Voer Uit',
        'en' => 'Export',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Exportar'
    ],
    'personal_information' => [
        'af' => 'Persoonlike Inligting',
        'en' => 'Personal Information',
        'zu' => 'Ulwazi Lomuntu Siqu',
        'xh' => 'Ulwazi Lobuqu',
        'pt' => 'Informações Pessoais'
    ],
    'church_information' => [
        'af' => 'Kerk Inligting',
        'en' => 'Church Information',
        'zu' => 'Ulwazi Lwebandla',
        'xh' => 'Ulwazi Lwecawa',
        'pt' => 'Informações da Igreja'
    ],
    'office' => [
        'af' => 'Amp',
        'en' => 'Office',
        'zu' => 'Isikhundla',
        'xh' => 'Isikhundla',
        'pt' => 'Cargo'
    ],
    'gender' => [
        'af' => 'Geslag',
        'en' => 'Gender',
        'zu' => 'Ubulili',
        'xh' => 'Isini',
        'pt' => 'Gênero'
    ],
    'view_profile' => [
        'af' => 'Kyk profiel',
        'en' => 'View profile',
        'zu' => 'Buka iphrofayela',
        'xh' => 'Jonga iprofayile',
        'pt' => 'Ver perfil'
    ],
    'edit_user' => [
        'af' => 'Wysig Gebruiker',
        'en' => 'Edit User',
        'zu' => 'Hlela Umsebenzisi',
        'xh' => 'Hlela Umsebenzisi',
        'pt' => 'Editar Usuário'
    ],

    // ==================== CALENDAR VIEW PAGE ====================
    'pdf' => [
        'af' => 'PDF',
        'en' => 'PDF',
        'zu' => 'PDF',
        'xh' => 'PDF',
        'pt' => 'PDF'
    ],
    'excel' => [
        'af' => 'Excel',
        'en' => 'Excel',
        'zu' => 'Excel',
        'xh' => 'Excel',
        'pt' => 'Excel'
    ],
    'generating_pdf' => [
        'af' => 'Genereer PDF...',
        'en' => 'Generating PDF...',
        'zu' => 'Kukhiqizwa i-PDF...',
        'xh' => 'Kuveliswa i-PDF...',
        'pt' => 'Gerando PDF...'
    ],
    'generating_excel' => [
        'af' => 'Genereer Excel...',
        'en' => 'Generating Excel...',
        'zu' => 'Kukhiqizwa i-Excel...',
        'xh' => 'Kuveliswa i-Excel...',
        'pt' => 'Gerando Excel...'
    ],
    'pdf_exported' => [
        'af' => 'PDF uitgevoer!',
        'en' => 'PDF exported!',
        'zu' => 'I-PDF ithunyelwe!',
        'xh' => 'I-PDF ithunyelwe!',
        'pt' => 'PDF exportado!'
    ],
    'excel_exported' => [
        'af' => 'Excel uitgevoer!',
        'en' => 'Excel exported!',
        'zu' => 'I-Excel ithunyelwe!',
        'xh' => 'I-Excel ithunyelwe!',
        'pt' => 'Excel exportado!'
    ],
    'export_failed' => [
        'af' => 'Uitvoer het misluk',
        'en' => 'Export failed',
        'zu' => 'Ukuthumela kwehlulekile',
        'xh' => 'Ukuthumela akuphumelelanga',
        'pt' => 'Exportação falhou'
    ],

    // ==================== NOTIFICATIONS PAGE ====================
    'ai_notifications' => [
        'af' => 'AI Kennisgewings',
        'en' => 'AI Notifications',
        'zu' => 'Izaziso ze-AI',
        'xh' => 'Izaziso ze-AI',
        'pt' => 'Notificações IA'
    ],
    'stay_updated' => [
        'af' => 'Bly Op Hoogte van Jou Reis',
        'en' => 'Stay Updated on Your Journey',
        'zu' => 'Hlala Ufundiswe Ngohambo Lwakho',
        'xh' => 'Hlala Uhlaziwe Ngohambo Lwakho',
        'pt' => 'Mantenha-se Atualizado na Sua Jornada'
    ],
    'mark_all_read' => [
        'af' => 'Merk Alles as Gelees',
        'en' => 'Mark All as Read',
        'zu' => 'Phawula Konke Njengokufundiwe',
        'xh' => 'Phawula Konke Njengokufundiweyo',
        'pt' => 'Marcar Tudo como Lido'
    ],
    'filter' => [
        'af' => 'Filter',
        'en' => 'Filter',
        'zu' => 'Hlunga',
        'xh' => 'Hlela',
        'pt' => 'Filtrar'
    ],
    'unread' => [
        'af' => 'Ongelees',
        'en' => 'Unread',
        'zu' => 'Okungafundiwe',
        'xh' => 'Okungafundiwe',
        'pt' => 'Não Lido'
    ],
    'reminders' => [
        'af' => 'Herrineringe',
        'en' => 'Reminders',
        'zu' => 'Izikhumbuzi',
        'xh' => 'Izikhumbuzo',
        'pt' => 'Lembretes'
    ],
    'info' => [
        'af' => 'Inligting',
        'en' => 'Info',
        'zu' => 'Ulwazi',
        'xh' => 'Ulwazi',
        'pt' => 'Info'
    ],
    'your_notifications' => [
        'af' => 'Jou Kennisgewings',
        'en' => 'Your Notifications',
        'zu' => 'Izaziso Zakho',
        'xh' => 'Izaziso Zakho',
        'pt' => 'Suas Notificações'
    ],
    'no_notifications' => [
        'af' => 'Geen kennisgewings nie',
        'en' => 'No notifications',
        'zu' => 'Akukho zaziso',
        'xh' => 'Akukho zaziso',
        'pt' => 'Sem notificações'
    ],
    'mark_as_read' => [
        'af' => 'Merk as gelees',
        'en' => 'Mark as read',
        'zu' => 'Phawula njengokufundiwe',
        'xh' => 'Phawula njengokufundiweyo',
        'pt' => 'Marcar como lido'
    ],
    'are_you_sure' => [
        'af' => 'Is jy seker?',
        'en' => 'Are you sure?',
        'zu' => 'Uqinisekile?',
        'xh' => 'Uqinisekile?',
        'pt' => 'Tem certeza?'
    ],
    'just_now' => [
        'af' => 'Nou net',
        'en' => 'Just now',
        'zu' => 'Manje nje',
        'xh' => 'Ngoku nje',
        'pt' => 'Agora mesmo'
    ],
    'minutes_ago' => [
        'af' => 'minute gelede',
        'en' => 'minutes ago',
        'zu' => 'amaminithi edlule',
        'xh' => 'imizuzu edlulileyo',
        'pt' => 'minutos atrás'
    ],
    'hours_ago' => [
        'af' => 'ure gelede',
        'en' => 'hours ago',
        'zu' => 'amahora edlule',
        'xh' => 'iiyure ezidlulileyo',
        'pt' => 'horas atrás'
    ],
    'days_ago' => [
        'af' => 'dae gelede',
        'en' => 'days ago',
        'zu' => 'izinsuku ezedlule',
        'xh' => 'iintsuku ezidlulileyo',
        'pt' => 'dias atrás'
    ],

    // ==================== SING EMMANUEL PAGE ====================
    'sing_emmanuel_title' => [
        'af' => 'Sing Emmanuel - Getuienis van Geloof',
        'en' => 'Sing Emmanuel - Testimony of Faith',
        'zu' => 'Cula Emmanuel - Ubufakazi Bokholo',
        'xh' => 'Cula Emmanuel - Ubungqina Bokholo',
        'pt' => 'Cantar Emmanuel - Testemunho de Fé'
    ],
    'praise_lord_song' => [
        'af' => 'Loof die Here met sang en jubel',
        'en' => 'Praise the Lord with song and jubilation',
        'zu' => 'Dumisani iNkosi ngengoma nokujabula',
        'xh' => 'Dumisani iNkosi ngengoma nokuvuya',
        'pt' => 'Louvai ao Senhor com cânticos e júbilo'
    ],
    'choose_song' => [
        'af' => 'Kies \'n Lied',
        'en' => 'Choose a Song',
        'zu' => 'Khetha Ingoma',
        'xh' => 'Khetha Ingoma',
        'pt' => 'Escolha uma Canção'
    ],
    'search_song' => [
        'af' => 'Soek Lied',
        'en' => 'Search Song',
        'zu' => 'Sesha Ingoma',
        'xh' => 'Khangela Ingoma',
        'pt' => 'Pesquisar Canção'
    ],
    'hymn' => [
        'af' => 'Lied',
        'en' => 'Hymn',
        'zu' => 'Ihubo',
        'xh' => 'Iculo',
        'pt' => 'Hino'
    ],
    'song_not_found' => [
        'af' => 'Lied nie gevind nie.',
        'en' => 'Song not found.',
        'zu' => 'Ingoma ayitholakali.',
        'xh' => 'Ingoma ayifumaneki.',
        'pt' => 'Canção não encontrada.'
    ],

    // ==================== AI SLIMBYBEL PAGE ====================
    'ai_slimbybel' => [
        'af' => 'AI Slimbybel',
        'en' => 'AI Smart Bible',
        'zu' => 'I-AI Slimbybel',
        'xh' => 'I-AI Slimbybel',
        'pt' => 'IA Bíblia Inteligente'
    ],
    'spiritual_wisdom_ai' => [
        'af' => 'Geestelike Wysheid deur Kunsmatige Intelligensie',
        'en' => 'Spiritual Wisdom through Artificial Intelligence',
        'zu' => 'Ukuhlakanipha Okungokomoya nge-AI',
        'xh' => 'Ubulumko Bokomoya nge-AI',
        'pt' => 'Sabedoria Espiritual através de Inteligência Artificial'
    ],
    'ask_scripture' => [
        'af' => 'Vra die Skrif',
        'en' => 'Ask the Scripture',
        'zu' => 'Buza Izibhalo',
        'xh' => 'Buza Izibhalo',
        'pt' => 'Pergunte às Escrituras'
    ],
    'ask_placeholder' => [
        'af' => 'bv. Wat beteken water in die Bybel?',
        'en' => 'e.g. What does water mean in the Bible?',
        'zu' => 'isb. Amanzi asho ukuthini eBhayibhelini?',
        'xh' => 'umz. Amanzi athetha ukuthini eBhayibhileni?',
        'pt' => 'ex. O que significa água na Bíblia?'
    ],
    'explain' => [
        'af' => 'Verduidelik',
        'en' => 'Explain',
        'zu' => 'Chaza',
        'xh' => 'Cacisa',
        'pt' => 'Explicar'
    ],
    'spiritual_interpretation_help' => [
        'af' => 'Geestelike uitleg • Afrikaans 1933/1953 • Skrif-met-Skrif',
        'en' => 'Spiritual interpretation • KJV • Scripture with Scripture',
        'zu' => 'Incazelo engokomoya • KJV • Izibhalo ngezibhalo',
        'xh' => 'Inkcazelo engokomoya • KJV • Izibhalo ngezibhalo',
        'pt' => 'Interpretação espiritual • ARC • Escritura com Escritura'
    ],
    'answer' => [
        'af' => 'Antwoord',
        'en' => 'Answer',
        'zu' => 'Impendulo',
        'xh' => 'Impendulo',
        'pt' => 'Resposta'
    ],
    'answer_placeholder' => [
        'af' => 'Jou antwoord sal hier in real-time verskyn...',
        'en' => 'Your answer will stream here in real time...',
        'zu' => 'Impendulo yakho izovela lapha ngesikhathi sangempela...',
        'xh' => 'Impendulo yakho iya kuvela apha ngexesha lokwenyani...',
        'pt' => 'Sua resposta aparecerá aqui em tempo real...'
    ],
    'example_questions' => [
        'af' => 'Voorbeeldvrae',
        'en' => 'Example Questions',
        'zu' => 'Imibuzo Yesibonelo',
        'xh' => 'Imibuzo Yomzekelo',
        'pt' => 'Perguntas de Exemplo'
    ],
    'empty_question' => [
        'af' => 'Leë vraag.',
        'en' => 'Empty question.',
        'zu' => 'Umbuzo ongenalutho.',
        'xh' => 'Umbuzo ongenanto.',
        'pt' => 'Pergunta vazia.'
    ],
    'missing_api_key' => [
        'af' => 'Geen OPENAI_API_KEY gevind nie.',
        'en' => 'Missing OPENAI_API_KEY.',
        'zu' => 'I-OPENAI_API_KEY ayitholakali.',
        'xh' => 'I-OPENAI_API_KEY ayifumaneki.',
        'pt' => 'OPENAI_API_KEY não encontrada.'
    ],
    'water_question_full' => [
        'af' => 'Wat beteken water in die Bybel?',
        'en' => 'What does water mean in the Bible?',
        'zu' => 'Amanzi asho ukuthini eBhayibhelini?',
        'xh' => 'Amanzi athetha ukuthini eBhayibhileni?',
        'pt' => 'O que significa água na Bíblia?'
    ],
    'water_question_short' => [
        'af' => 'Wat beteken water?',
        'en' => 'What does water mean?',
        'zu' => 'Amanzi asho ukuthini?',
        'xh' => 'Amanzi athetha ukuthini?',
        'pt' => 'O que significa água?'
    ],
    'sower_question_full' => [
        'af' => 'Verduidelik die gelykenis van die saaier',
        'en' => 'Explain the parable of the sower',
        'zu' => 'Chaza umfanekiso womhlwanyeli',
        'xh' => 'Cacisa umzekeliso womhlwayeli',
        'pt' => 'Explique a parábola do semeador'
    ],
    'sower_question_short' => [
        'af' => 'Gelykenis van die saaier',
        'en' => 'Parable of the sower',
        'zu' => 'Umfanekiso womhlwanyeli',
        'xh' => 'Umzekeliso womhlwayeli',
        'pt' => 'Parábola do semeador'
    ],
    'spirit_question_full' => [
        'af' => 'Wat is die vrug van die Gees?',
        'en' => 'What is the fruit of the Spirit?',
        'zu' => 'Yini isithelo sikaMoya?',
        'xh' => 'Yintoni isiqhamo soMoya?',
        'pt' => 'Qual é o fruto do Espírito?'
    ],
    'spirit_question_short' => [
        'af' => 'Vrug van die Gees',
        'en' => 'Fruit of the Spirit',
        'zu' => 'Isithelo sikaMoya',
        'xh' => 'Isiqhamo soMoya',
        'pt' => 'Fruto do Espírito'
    ],
    'kingdom_question_full' => [
        'af' => 'Verduidelik die Koninkryk van God',
        'en' => 'Explain the Kingdom of God',
        'zu' => 'Chaza uMbuso kaNkulunkulu',
        'xh' => 'Cacisa uBukumkani bukaThixo',
        'pt' => 'Explique o Reino de Deus'
    ],
    'kingdom_question_short' => [
        'af' => 'Koninkryk van God',
        'en' => 'Kingdom of God',
        'zu' => 'UMbuso kaNkulunkulu',
        'xh' => 'UBukumkani bukaThixo',
        'pt' => 'Reino de Deus'
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
        'pt' => '✅ Conta Aprovada'
    ],
    'notif_account_approved_msg' => [
        'af' => 'Jou rekening is goedgekeur. Jy kan nou aanmeld.',
        'en' => 'Your account has been approved. You can now log in.',
        'zu' => 'I-akhawunti yakho yamukelwe. Ungangena manje.',
        'xh' => 'I-akhawunti yakho yamkelwe. Ungangena ngoku.',
        'pt' => 'Sua conta foi aprovada. Você pode entrar agora.'
    ],
    'notif_account_rejected' => [
        'af' => '❌ Account Afgekeur',
        'en' => '❌ Account Rejected',
        'zu' => '❌ I-akhawunti Yenqatshiwe',
        'xh' => '❌ I-akhawunti Yaliwe',
        'pt' => '❌ Conta Rejeitada'
    ],
    'notif_account_rejected_msg' => [
        'af' => 'Jou rekening is afgekeur. Kontak admin vir meer inligting.',
        'en' => 'Your account has been rejected. Contact admin for more information.',
        'zu' => 'I-akhawunti yakho yenqatshiwe. Xhumana nomphathi ukuze uthole ulwazi olwengeziwe.',
        'xh' => 'I-akhawunti yakho yaliwe. Qhagamshelana nomlawuli ukufumana ulwazi oluthe kratya.',
        'pt' => 'Sua conta foi rejeitada. Entre em contato com o administrador para mais informações.'
    ],
    'notif_spouse_request' => [
        'af' => '💍 Eggenoot Versoek',
        'en' => '💍 Spouse Request',
        'zu' => '💍 Isicelo Somngani',
        'xh' => '💍 Isicelo Somlingani',
        'pt' => '💍 Solicitação de Cônjuge'
    ],
    'notif_spouse_request_msg' => [
        'af' => '{name} wil met jou as eggenoot koppel.',
        'en' => '{name} wants to link with you as spouse.',
        'zu' => '{name} ufuna ukuxhumana nawe njengomngani.',
        'xh' => '{name} ufuna ukudibanisa nawe njengomlingani.',
        'pt' => '{name} quer se conectar com você como cônjuge.'
    ],
    'notif_spouse_accepted' => [
        'af' => '💕 Eggenoot Gekoppel',
        'en' => '💕 Spouse Linked',
        'zu' => '💕 Umngani Uxhunyiwe',
        'xh' => '💕 Umlingani Udibanisiwe',
        'pt' => '💕 Cônjuge Vinculado'
    ],
    'notif_spouse_accepted_msg' => [
        'af' => '{name} het jou versoek aanvaar!',
        'en' => '{name} accepted your request!',
        'zu' => '{name} wamukele isicelo sakho!',
        'xh' => '{name} wamkele isicelo sakho!',
        'pt' => '{name} aceitou seu pedido!'
    ],
    'notif_appointment_request' => [
        'af' => '📅 Afspraak Versoek',
        'en' => '📅 Appointment Request',
        'zu' => '📅 Isicelo Sesikhathi',
        'xh' => '📅 Isicelo Sokuhlangana',
        'pt' => '📅 Solicitação de Compromisso'
    ],
    'notif_appointment_request_msg' => [
        'af' => "{name} wil 'n afspraak maak op {date} om {time}.",
        'en' => '{name} wants to make an appointment on {date} at {time}.',
        'zu' => '{name} ufuna ukwenza isithembiso ngo-{date} ngo-{time}.',
        'xh' => '{name} ufuna ukwenza isigqibo ngo-{date} ngo-{time}.',
        'pt' => '{name} quer marcar um compromisso em {date} às {time}.'
    ],
    'notif_appointment_confirmed' => [
        'af' => '✅ Afspraak Bevestig',
        'en' => '✅ Appointment Confirmed',
        'zu' => '✅ Isikhathi Siqinisekisiwe',
        'xh' => '✅ Ukuhlangana Kuqinisekisiwe',
        'pt' => '✅ Compromisso Confirmado'
    ],
    'notif_appointment_confirmed_msg' => [
        'af' => 'Jou afspraak is bevestig!',
        'en' => 'Your appointment has been confirmed!',
        'zu' => 'Isithembiso sakho siqinisekisiwe!',
        'xh' => 'Isigqibo sakho siqinisekisiwe!',
        'pt' => 'Seu compromisso foi confirmado!'
    ],
    'notif_ampte_change' => [
        'af' => '⭐ Amp Verander',
        'en' => '⭐ Office Changed',
        'zu' => '⭐ Isikhundla Sishintshiwe',
        'xh' => '⭐ Isikhundla Sitshintshiwe',
        'pt' => '⭐ Cargo Alterado'
    ],
    'notif_ampte_change_msg' => [
        'af' => 'Jou amp is verander na: {amp}',
        'en' => 'Your office has been changed to: {amp}',
        'zu' => 'Isikhundla sakho sishintshwe saba: {amp}',
        'xh' => 'Isikhundla sakho sitshintshwe saba: {amp}',
        'pt' => 'Seu cargo foi alterado para: {amp}'
    ],

    // Gospel notifications
    'notif_new_post' => [
        'af' => '✍️ Nuwe Plasing',
        'en' => '✍️ New Post',
        'zu' => '✍️ Okutsha Okufakiwe',
        'xh' => '✍️ Okutsha Okufakiweyo',
        'pt' => '✍️ Nova Publicação'
    ],
    'notif_new_post_msg' => [
        'af' => '{name} het in {room} geplaas.',
        'en' => '{name} posted in {room}.',
        'zu' => '{name} ufake ku-{room}.',
        'xh' => '{name} ufake ku-{room}.',
        'pt' => '{name} publicou em {room}.'
    ],
    'notif_new_comment' => [
        'af' => '💬 Nuwe Kommentaar',
        'en' => '💬 New Comment',
        'zu' => '💬 Ukuphawula Okusha',
        'xh' => '💬 Ukuphawula Okutsha',
        'pt' => '💬 Novo Comentário'
    ],
    'notif_new_comment_msg' => [
        'af' => '{name} het op jou plasing gereageer.',
        'en' => '{name} commented on your post.',
        'zu' => '{name} uphawule okutsha sakho.',
        'xh' => '{name} uphawule okutsha sakho.',
        'pt' => '{name} comentou na sua publicação.'
    ],
    'notif_reaction' => [
        'af' => '{emoji} Reaksie',
        'en' => '{emoji} Reaction',
        'zu' => '{emoji} Ukusabela',
        'xh' => '{emoji} Ukusabela',
        'pt' => '{emoji} Reação'
    ],
    'notif_reaction_msg' => [
        'af' => '{name} het {emoji} op jou plasing gegee.',
        'en' => '{name} reacted with {emoji} to your post.',
        'zu' => '{name} usabele ngo-{emoji} ekutsha kwakho.',
        'xh' => '{name} usabele nge-{emoji} ekutsha kwakho.',
        'pt' => '{name} reagiu com {emoji} à sua publicação.'
    ],
    'notif_tagged' => [
        'af' => '🏷️ Ge-tag',
        'en' => '🏷️ Tagged',
        'zu' => '🏷️ Umakiwe',
        'xh' => '🏷️ Umakiwe',
        'pt' => '🏷️ Marcado'
    ],
    'notif_tagged_msg' => [
        'af' => "{name} het jou in 'n plasing ge-tag.",
        'en' => '{name} tagged you in a post.',
        'zu' => '{name} ukumake okutsha.',
        'xh' => '{name} ukumake okutsha.',
        'pt' => '{name} marcou você em uma publicação.'
    ],

    // Calendar notifications
    'notif_event_reminder' => [
        'af' => '⏰ Herinnering',
        'en' => '⏰ Reminder',
        'zu' => '⏰ Isikhumbuzo',
        'xh' => '⏰ Isikhumbuzo',
        'pt' => '⏰ Lembrete'
    ],
    'notif_event_reminder_msg' => [
        'af' => '{event} begin oor 30 minute om {time}',
        'en' => '{event} starts in 30 minutes at {time}',
        'zu' => '{event} iqala ngemizuzu engu-30 ngo-{time}',
        'xh' => '{event} iqala ngemizuzu engama-30 ngo-{time}',
        'pt' => '{event} começa em 30 minutos às {time}'
    ],
    'notif_event_shared' => [
        'af' => '📅 Aktiwiteit Gedeel',
        'en' => '📅 Activity Shared',
        'zu' => '📅 Umsebenzi Wabelwe',
        'xh' => '📅 Umsebenzi Wabelwe',
        'pt' => '📅 Atividade Compartilhada'
    ],
    'notif_event_shared_msg' => [
        'af' => "{name} het '{event}' met jou gedeel.",
        'en' => "{name} shared '{event}' with you.",
        'zu' => "{name} wabelane '{event}' nawe.",
        'xh' => "{name} wabelane '{event}' nawe.",
        'pt' => "{name} compartilhou '{event}' com você."
    ],
    'notif_diary_reminder' => [
        'af' => '📓 Dagboek Herinnering',
        'en' => '📓 Diary Reminder',
        'zu' => '📓 Isikhumbuzo Sedayari',
        'xh' => '📓 Isikhumbuzo Sedayari',
        'pt' => '📓 Lembrete do Diário'
    ],
    'notif_diary_reminder_msg' => [
        'af' => 'Onthou om jou dagboek vandag by te werk!',
        'en' => 'Remember to update your diary today!',
        'zu' => 'Khumbula ukuvuselela idayari yakho namhlanje!',
        'xh' => 'Khumbula ukuhlaziya idayari yakho namhlanje!',
        'pt' => 'Lembre-se de atualizar seu diário hoje!'
    ],
    'notif_visit_scheduled' => [
        'af' => '🏠 Besoek Geskeduleer',
        'en' => '🏠 Visit Scheduled',
        'zu' => '🏠 Ukuvakasha Kuhleliwe',
        'xh' => '🏠 Utyelelo Lucwangcisiwe',
        'pt' => '🏠 Visita Agendada'
    ],
    'notif_visit_scheduled_msg' => [
        'af' => 'Besoek by {name} op {date}',
        'en' => 'Visit to {name} on {date}',
        'zu' => 'Ukuvakasha ku-{name} ngo-{date}',
        'xh' => 'Utyelelo ku-{name} ngo-{date}',
        'pt' => 'Visita a {name} em {date}'
    ],

    // Generic notification placeholders
    'notif_someone' => [
        'af' => 'Iemand',
        'en' => 'Someone',
        'zu' => 'Othile',
        'xh' => 'Omnye umntu',
        'pt' => 'Alguém'
    ],

    // ==================== BIRTHDAY NOTIFICATIONS ====================
    'happy_birthday' => [
        'af' => 'Gelukkige Verjaarsdag!',
        'en' => 'Happy Birthday!',
        'zu' => 'Usuku lokuzalwa oluhle!',
        'xh' => 'Usuku lokuzalwa olumnandi!',
        'pt' => 'Feliz Aniversário!'
    ],
    'birthday_self_msg' => [
        'af' => 'Baie geluk met jou verjaarsdag! Mag die Here jou seën.',
        'en' => 'Congratulations on your birthday! May God bless you.',
        'zu' => 'Siyakuhalalisela ngosuku lwakho lokuzalwa! Sengathi uNkulunkulu angakubusisa.',
        'xh' => 'Sivuyisana nawe ngosuku lwakho lokuzalwa! Ngamana uThixo akusikelele.',
        'pt' => 'Parabéns pelo seu aniversário! Que Deus te abençoe.'
    ],
    'birthday_notification' => [
        'af' => 'Verjaarsdag Vandag',
        'en' => 'Birthday Today',
        'zu' => 'Usuku Lokuzalwa Namhlanje',
        'xh' => 'Usuku Lokuzalwa Namhlanje',
        'pt' => 'Aniversário Hoje'
    ],
    'birthday_notification_msg' => [
        'af' => '{name} vier vandag verjaarsdag!',
        'en' => '{name} is celebrating their birthday today!',
        'zu' => '{name} ugubha usuku lwakhe lokuzalwa namhlanje!',
        'xh' => '{name} ubhiyozela usuku lwakhe lokuzalwa namhlanje!',
        'pt' => '{name} está comemorando seu aniversário hoje!'
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
        'pt' => 'Editar'
    ],
    'js_delete' => [
        'af' => 'Verwyder',
        'en' => 'Delete',
        'zu' => 'Susa',
        'xh' => 'Cima',
        'pt' => 'Excluir'
    ],
    'js_cancel' => [
        'af' => 'Kanselleer',
        'en' => 'Cancel',
        'zu' => 'Khansela',
        'xh' => 'Rhoxisa',
        'pt' => 'Cancelar'
    ],
    'js_save' => [
        'af' => 'Stoor',
        'en' => 'Save',
        'zu' => 'Londoloza',
        'xh' => 'Gcina',
        'pt' => 'Salvar'
    ],
    'js_saving' => [
        'af' => 'Stoor...',
        'en' => 'Saving...',
        'zu' => 'Ilondoloza...',
        'xh' => 'Igcina...',
        'pt' => 'Salvando...'
    ],
    'js_back' => [
        'af' => 'Terug',
        'en' => 'Back',
        'zu' => 'Emuva',
        'xh' => 'Emva',
        'pt' => 'Voltar'
    ],
    'js_error' => [
        'af' => 'Fout',
        'en' => 'Error',
        'zu' => 'Iphutha',
        'xh' => 'Impazamo',
        'pt' => 'Erro'
    ],
    'js_loading' => [
        'af' => 'Laai...',
        'en' => 'Loading...',
        'zu' => 'Iyalayisha...',
        'xh' => 'Iyalayisha...',
        'pt' => 'Carregando...'
    ],

    // Post actions
    'js_post_actions' => [
        'af' => 'Post aksies',
        'en' => 'Post actions',
        'zu' => 'Izenzo zokuthunyelwe',
        'xh' => 'Izenzo zeposti',
        'pt' => 'Ações da postagem'
    ],
    'js_edit_post' => [
        'af' => 'Wysig plasing',
        'en' => 'Edit post',
        'zu' => 'Hlela okuthunyelwe',
        'xh' => 'Hlela iposti',
        'pt' => 'Editar postagem'
    ],
    'js_confirm_delete' => [
        'af' => 'Bevestig verwydering',
        'en' => 'Confirm deletion',
        'zu' => 'Qinisekisa ukususa',
        'xh' => 'Qinisekisa ukucima',
        'pt' => 'Confirmar exclusão'
    ],
    'js_cannot_undo' => [
        'af' => 'Hierdie aksie kan nie ontdoen word nie',
        'en' => 'This action cannot be undone',
        'zu' => 'Lesi senzo asikwazi ukuhlehliswa',
        'xh' => 'Esi senzo asinakubuyiselwa',
        'pt' => 'Esta ação não pode ser desfeita'
    ],
    'js_deleting' => [
        'af' => 'Verwyder...',
        'en' => 'Deleting...',
        'zu' => 'Iyasusa...',
        'xh' => 'Iyacima...',
        'pt' => 'Excluindo...'
    ],
    'js_post' => [
        'af' => 'Plaas',
        'en' => 'Post',
        'zu' => 'Thumela',
        'xh' => 'Thumela',
        'pt' => 'Postar'
    ],
    'js_posting' => [
        'af' => 'Plaas...',
        'en' => 'Posting...',
        'zu' => 'Iyathumela...',
        'xh' => 'Iyathumela...',
        'pt' => 'Postando...'
    ],
    'js_type_message' => [
        'af' => 'Tik asseblief \'n boodskap',
        'en' => 'Please type a message',
        'zu' => 'Sicela ubhale umyalezo',
        'xh' => 'Nceda bhala umyalezo',
        'pt' => 'Por favor, digite uma mensagem'
    ],
    'js_error_posting' => [
        'af' => 'Fout met plaas',
        'en' => 'Error posting',
        'zu' => 'Iphutha lokuthumela',
        'xh' => 'Impazamo yokuthumela',
        'pt' => 'Erro ao postar'
    ],
    'js_loading_posts' => [
        'af' => 'Laai plasings...',
        'en' => 'Loading posts...',
        'zu' => 'Ilayisha izithunyelwe...',
        'xh' => 'Ilayisha iiposti...',
        'pt' => 'Carregando postagens...'
    ],
    'js_no_posts' => [
        'af' => 'Geen plasings in hierdie kamer nie',
        'en' => 'No posts in this room',
        'zu' => 'Akukho okuthunyelwe kuleli kamelo',
        'xh' => 'Akukho ziposti kule ndawo',
        'pt' => 'Nenhuma postagem nesta sala'
    ],

    // Comments
    'js_comments' => [
        'af' => 'Kommentare',
        'en' => 'Comments',
        'zu' => 'Izimphawulo',
        'xh' => 'Izimvo',
        'pt' => 'Comentários'
    ],
    'js_write_comment' => [
        'af' => 'Skryf \'n kommentaar...',
        'en' => 'Write a comment...',
        'zu' => 'Bhala ukuphawula...',
        'xh' => 'Bhala isimvo...',
        'pt' => 'Escreva um comentário...'
    ],
    'js_no_comments' => [
        'af' => 'Geen kommentare nog nie',
        'en' => 'No comments yet',
        'zu' => 'Akukho zimphawulo okwamanje',
        'xh' => 'Akukho zimvo okwangoku',
        'pt' => 'Nenhum comentário ainda'
    ],
    'js_delete_comment' => [
        'af' => 'Verwyder hierdie kommentaar?',
        'en' => 'Delete this comment?',
        'zu' => 'Susa lesi simphawulo?',
        'xh' => 'Cima le simvo?',
        'pt' => 'Excluir este comentário?'
    ],
    'js_edit_comment' => [
        'af' => 'Wysig kommentaar:',
        'en' => 'Edit comment:',
        'zu' => 'Hlela ukuphawula:',
        'xh' => 'Hlela isimvo:',
        'pt' => 'Editar comentário:'
    ],

    // Photos
    'js_add_photo' => [
        'af' => 'Voeg foto by',
        'en' => 'Add photo',
        'zu' => 'Engeza isithombe',
        'xh' => 'Yongeza umfanekiso',
        'pt' => 'Adicionar foto'
    ],
    'js_remove_photo' => [
        'af' => 'Verwyder foto',
        'en' => 'Remove photo',
        'zu' => 'Susa isithombe',
        'xh' => 'Susa umfanekiso',
        'pt' => 'Remover foto'
    ],
    'js_delete_photo' => [
        'af' => 'Verwyder hierdie foto?',
        'en' => 'Remove this photo?',
        'zu' => 'Susa lesi sithombe?',
        'xh' => 'Susa lo mfanekiso?',
        'pt' => 'Remover esta foto?'
    ],

    // Events
    'js_datetime' => [
        'af' => 'Datum/tyd',
        'en' => 'Date/time',
        'zu' => 'Usuku/isikhathi',
        'xh' => 'Umhla/ixesha',
        'pt' => 'Data/hora'
    ],
    'js_place' => [
        'af' => 'Plek',
        'en' => 'Place',
        'zu' => 'Indawo',
        'xh' => 'Indawo',
        'pt' => 'Local'
    ],

    // Prayers specific
    'js_share' => [
        'af' => 'Deel',
        'en' => 'Share',
        'zu' => 'Yabelana',
        'xh' => 'Yabelana',
        'pt' => 'Compartilhar'
    ],
    'js_update' => [
        'af' => 'Opdateer',
        'en' => 'Update',
        'zu' => 'Buyekeza',
        'xh' => 'Hlaziya',
        'pt' => 'Atualizar'
    ],
    'js_choose_photo' => [
        'af' => 'Kies foto (opsioneel)',
        'en' => 'Choose photo (optional)',
        'zu' => 'Khetha isithombe (okungenasidingo)',
        'xh' => 'Khetha umfanekiso (ayimfuneko)',
        'pt' => 'Escolher foto (opcional)'
    ],
    'js_prayer_shared' => [
        'af' => 'Gebedsnood/getuienis gedeel!',
        'en' => 'Prayer/testimony shared!',
        'zu' => 'Umthandazo/ubufakazi buhlelwe!',
        'xh' => 'Umthandazo/ubungqina babelwe!',
        'pt' => 'Oração/testemunho compartilhado!'
    ],
    'js_could_not_post' => [
        'af' => 'Kon nie plaas nie',
        'en' => 'Could not post',
        'zu' => 'Ayikwazanga ukuthumela',
        'xh' => 'Ayikwazanga ukuthumela',
        'pt' => 'Não foi possível postar'
    ],
    'js_post_updated' => [
        'af' => 'Post opgedateer!',
        'en' => 'Post updated!',
        'zu' => 'Okuthunyelwe kubuyekeziwe!',
        'xh' => 'Iposti ihlaziyiwe!',
        'pt' => 'Postagem atualizada!'
    ],
    'js_could_not_update' => [
        'af' => 'Kon nie opdateer nie',
        'en' => 'Could not update',
        'zu' => 'Ayikwazanga ukubuyekeza',
        'xh' => 'Ayikwazanga ukuhlaziya',
        'pt' => 'Não foi possível atualizar'
    ],
    'js_photo_removed' => [
        'af' => 'Foto verwyder!',
        'en' => 'Photo removed!',
        'zu' => 'Isithombe sisusiwe!',
        'xh' => 'Umfanekiso ususwe!',
        'pt' => 'Foto removida!'
    ],
    'js_could_not_remove' => [
        'af' => 'Kon nie verwyder nie',
        'en' => 'Could not remove',
        'zu' => 'Ayikwazanga ukususa',
        'xh' => 'Ayikwazanga ukususa',
        'pt' => 'Não foi possível remover'
    ],
    'js_delete_post' => [
        'af' => 'Wis hierdie post uit?',
        'en' => 'Delete this post?',
        'zu' => 'Susa le posti?',
        'xh' => 'Cima le posti?',
        'pt' => 'Excluir esta postagem?'
    ],
    'js_post_deleted' => [
        'af' => 'Post uitgewis!',
        'en' => 'Post deleted!',
        'zu' => 'Okuthunyelwe kususiwe!',
        'xh' => 'Iposti icinyiwe!',
        'pt' => 'Postagem excluída!'
    ],
    'js_could_not_delete' => [
        'af' => 'Kon nie uitwis nie',
        'en' => 'Could not delete',
        'zu' => 'Ayikwazanga ukususa',
        'xh' => 'Ayikwazanga ukucima',
        'pt' => 'Não foi possível excluir'
    ],
    'js_loading_prayers' => [
        'af' => 'Laai gebede...',
        'en' => 'Loading prayers...',
        'zu' => 'Ilayisha imithandazo...',
        'xh' => 'Ilayisha imithandazo...',
        'pt' => 'Carregando orações...'
    ],
    'js_no_prayers' => [
        'af' => 'Geen gebede/getuienisse gevind nie',
        'en' => 'No prayers/testimonies found',
        'zu' => 'Akukho mithandazo/ubufakazi okutholiwe',
        'xh' => 'Akukho mithandazo/ubungqina bufunyenweyo',
        'pt' => 'Nenhuma oração/testemunho encontrado'
    ],
    'js_could_not_load' => [
        'af' => 'Kon nie laai nie',
        'en' => 'Could not load',
        'zu' => 'Ayikwazanga ukulayisha',
        'xh' => 'Ayikwazanga ukulayisha',
        'pt' => 'Não foi possível carregar'
    ],
    'js_prayer' => [
        'af' => 'Gebed',
        'en' => 'Prayer',
        'zu' => 'Umthandazo',
        'xh' => 'Umthandazo',
        'pt' => 'Oração'
    ],
    'js_testimony' => [
        'af' => 'Getuienis',
        'en' => 'Testimony',
        'zu' => 'Ubufakazi',
        'xh' => 'Ubungqina',
        'pt' => 'Testemunho'
    ],
    'js_comment_posted' => [
        'af' => 'Kommentaar geplaas!',
        'en' => 'Comment posted!',
        'zu' => 'Ukuphawula kuthunyelwe!',
        'xh' => 'Isimvo sithunyelelwe!',
        'pt' => 'Comentário postado!'
    ],
    'js_comment_updated' => [
        'af' => 'Kommentaar opgedateer!',
        'en' => 'Comment updated!',
        'zu' => 'Ukuphawula kubuyekeziwe!',
        'xh' => 'Isimvo sihlaziyiwe!',
        'pt' => 'Comentário atualizado!'
    ],
    'js_comment_deleted' => [
        'af' => 'Kommentaar uitgewis!',
        'en' => 'Comment deleted!',
        'zu' => 'Ukuphawula kususiwe!',
        'xh' => 'Isimvo sicinyiwe!',
        'pt' => 'Comentário excluído!'
    ],
    'js_remove' => [
        'af' => 'Verwyder',
        'en' => 'Remove',
        'zu' => 'Susa',
        'xh' => 'Susa',
        'pt' => 'Remover'
    ],
    // Rooms menu translations
    'js_rooms' => [
        'af' => 'Kamers',
        'en' => 'Rooms',
        'zu' => 'Amagumbi',
        'xh' => 'Amagumbi',
        'pt' => 'Salas'
    ],
    'js_manage_rooms' => [
        'af' => 'Bestuur Kamers',
        'en' => 'Manage Rooms',
        'zu' => 'Phatha Amagumbi',
        'xh' => 'Lawula Amagumbi',
        'pt' => 'Gerenciar Salas'
    ],
    'js_loading_rooms' => [
        'af' => 'Laai kamers…',
        'en' => 'Loading rooms…',
        'zu' => 'Iyalayisha amagumbi…',
        'xh' => 'Ilayisha amagumbi…',
        'pt' => 'Carregando salas…'
    ],
    'js_no_rooms' => [
        'af' => 'Geen kamers nie',
        'en' => 'No rooms',
        'zu' => 'Awekho amagumbi',
        'xh' => 'Akukho magumbi',
        'pt' => 'Nenhuma sala'
    ],
    'js_automatic' => [
        'af' => 'Outomaties',
        'en' => 'Automatic',
        'zu' => 'Okuzenzakalelayo',
        'xh' => 'Ngokuzenzekelayo',
        'pt' => 'Automático'
    ],
    'js_auto' => [
        'af' => 'Outo',
        'en' => 'Auto',
        'zu' => 'Outo',
        'xh' => 'Outo',
        'pt' => 'Auto'
    ],
    'js_joined_section' => [
        'af' => 'Gekose',
        'en' => 'Joined',
        'zu' => 'Kujoyinwe',
        'xh' => 'Kujoyinwe',
        'pt' => 'Inscritas'
    ],
    'js_joined_badge' => [
        'af' => 'Gekies',
        'en' => 'Joined',
        'zu' => 'Kujoyinwe',
        'xh' => 'Kujoyinwe',
        'pt' => 'Inscrita'
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
