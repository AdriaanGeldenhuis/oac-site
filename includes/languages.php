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
        'af' => 'Ouderling',
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
        'af' => 'Vra AI',
        'en' => 'Ask AI',
        'zu' => 'Buza i-AI',
        'xh' => 'Buza i-AI',
        'pt' => 'Perguntar à IA'
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
    'create_new' => [
        'af' => 'Skep Nuwe',
        'en' => 'Create New',
        'zu' => 'Dala Okusha',
        'xh' => 'Yenza Okutsha',
        'pt' => 'Criar Novo'
    ],
    'event' => [
        'af' => 'Gebeurtenis',
        'en' => 'Event',
        'zu' => 'Isenzakalo',
        'xh' => 'Isiganeko',
        'pt' => 'Evento'
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
        'af' => 'Geen gebeure',
        'en' => 'No events',
        'zu' => 'Akukho zenzakalo',
        'xh' => 'Akukho ziganeko',
        'pt' => 'Sem eventos'
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
