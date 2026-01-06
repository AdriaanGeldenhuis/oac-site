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
