<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>La Maison Tourangelle</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style type="text/css">
        header img, .code {
            margin-left: auto;
            margin-right: auto;
        }

        p {
            font-size: 11px;
            line-height: normal;
        }

        .logo img {
    display: flex;
    justify-content: center;
    width: 20em;
        }

        .message {
            text-align: center;
        }
        span {
            font-weight: bold;
        }

        .souligne {
            text-decoration: underline;
        }

        .cadeau p, .code{
            background-color: #a68f4c;
            width: 40%;
            font-weight: bold;
            padding: 1em;
        }

    </style>
</head>
<body>
<header>
    <div class="logo">
        <img  src="http://www.lamaisontourangelle.com/wp-content/uploads/LOGO-ENTIER-e1565615892530.jpg"><br>
    </div>

    <div class="logo">
        <img  src="http://www.lamaisontourangelle.com/wp-content/uploads/entete.jpg">
    </div>
    
</header>
<main>
    
    <div class="civilite">
        <p>Madame, Monsieur,</p>
    </div>
    <div class="message">
        
        <p>Vous venez de recevoir une invitation pour 2 personnes,<br>
        <span>de la part de <?php echo $client_name ?> avec le message suivant : "<?php echo $friend_message; ?>"</span><br>
        à La Maison Tourangelle, entre roc et tuffeaux, dans une ancienne chartreuse,<br>
        transformée en auberge de charme, où il vous sera servi un repas gastronomique.</p>

        <p><span>Nous vous demanderons de bien vouloir réserver votre table au 02 47 50 30 05,<br>
            uniquement, et de nous communiquer la référence n°: <p>

        <p class="code"><?php echo $coupon_code ?></span></p>

        <p class="souligne"><span>Nous vous demanderons de nous présenter ce bon cadeau à votre arrivée.</span></p>
        <p>Dans l’attente du plaisir de vous recevoir dans les murs de La Maison Tourangelle,<br>
            veuillez recevoir, Madame Monsieur, l’expression de nos respectueuses salutations.</p>
        <p>Frédéric ARNAULT</p>
    </div>
    <div class="cadeau">
        <p>Mme, M. <?php echo $name; ?> <br>
        Date de validité <?php echo date_i18n( 'd F Y', $coupon_expire ) ?></p>
    </div>
    <div>
        <p>Le Restaurant est ouvert du Mercredi au Dimanche midi.</p>
        <p>Le bon cadeau n’est pas consommable les jours fériés suivants :
        ...Noël, Saint-Sylvestre et Jour de l’An, Saint-Valentin, Dimanche de Pâques, Fête des Mères...</p>
        <p>La Maison Tourangelle, 9 route des Grottes Pétrifiantes 37510 Savonnières</p>
    </div>
</main>
</body>
</html>