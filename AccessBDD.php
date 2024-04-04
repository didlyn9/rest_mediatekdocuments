<?php
include_once("ConnexionPDO.php");
include_once("loadEnv.php");

loadEnv();

/**
 * Classe de construction des requêtes SQL à envoyer à la BDD
 */
class AccessBDD {

    public function getConfig($varName) {
        return getenv($varName);
    }
	
    public $login;
    public $mdp;
    public $bd;
    public $serveur;
    public $port;
    public $conn = null;

    /**
     * constructeur : demande de connexion à la BDD
     */
    public function __construct(){

        $this->login = getenv('DB_LOGIN');
        $this->mdp = getenv('DB_PASSWORD');
        $this->bd = getenv('DB_NAME');
        $this->serveur = getenv('DB_SERVER');
        $this->port = getenv('DB_PORT');
        
        try{
            $this->conn = new ConnexionPDO($this->login, $this->mdp, $this->bd, $this->serveur, $this->port);
        }catch(Exception $e){
            throw $e;
        }
    }

    /**
     * récupération de toutes les lignes d'une table
     * @param string $table nom de la table
     * @return lignes de la requete
     */
    public function selectAll($table){
        if($this->conn != null){
            switch ($table) {
                case "livre" :
                    return $this->selectAllLivres();
                case "dvd" :
                    return $this->selectAllDvd();
                case "revue" :
                    return $this->selectAllRevues();
                case "exemplaire" :
                    return $this->selectExemplairesRevue();
                case "maxcommande" :
                    return $this->selectMaxCommande();
                case "genre" :
                case "public" :
                case "rayon" :
                    // select portant sur une table contenant juste id et libelle
                    return $this->selectTableSimple($table);
                default:
                    // select portant sur une table, sans condition
                    return $this->selectTable($table);
            }
        }else{
            return null;
        }
    }

    /**
     * récupération des lignes concernées
     * @param string $table nom de la table
     * @param array $champs nom et valeur de chaque champs de recherche
     * @return lignes répondant aux critères de recherches
     */	
    public function select($table, $champs){
        if($this->conn != null && $champs != null){
            switch($table){
                case "exemplaire" :
                    return $this->selectExemplairesRevue($champs['id']);
                case "commandedocument" :
                    return $this->selectCommandesDocument($champs['idLivreDvd']);
                case "abonnements" :
                    return $this->selectAbonnementsRevue($champs['idRevue']);
                case "utilisateur" :
                    return $this->selectUtilisateur($champs);
                
                default:                    
                    // cas d'un select sur une table avec recherche sur des champs
                    return $this->selectTableOnConditons($table, $champs);
            }
        }else{
                return null;
        }
    }

    /**
     * récupération de toutes les lignes d'une table simple (qui contient juste id et libelle)
     * @param string $table
     * @return lignes triées sur lebelle
     */
    public function selectTableSimple($table){
        $req = "select * from $table order by libelle;";		
        return $this->conn->query($req);	    
    }
    
    /**
     * récupération de toutes les lignes d'une table
     * @param string $table
     * @return toutes les lignes de la table
     */
    public function selectTable($table){
        $req = "select * from $table;";		
        return $this->conn->query($req);        
    }
    
    /**
     * récupération des lignes d'une table dont les champs concernés correspondent aux valeurs
     * @param type $table
     * @param type $champs
     * @return type
     */
    public function selectTableOnConditons($table, $champs){
        // construction de la requête
        $requete = "select * from $table where ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key and";
        }
        // (enlève le dernier and)
        $requete = substr($requete, 0, strlen($requete)-3);
        return $this->conn->query($requete, $champs);
    }

    /**
     * récupération de toutes les lignes de la table Livre et les tables associées
     * @return lignes de la requete
     */
    public function selectAllLivres(){
        $req = "Select l.id, l.ISBN, l.auteur, d.titre, d.image, l.collection, ";
        $req .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $req .= "from livre l join document d on l.id=d.id ";
        $req .= "join genre g on g.id=d.idGenre ";
        $req .= "join public p on p.id=d.idPublic ";
        $req .= "join rayon r on r.id=d.idRayon ";
        $req .= "order by titre ";		
        return $this->conn->query($req);
    }

    /**
     * récupération de toutes les lignes de la table DVD et les tables associées
     * @return lignes de la requete
     */
    public function selectAllDvd(){
        $req = "Select l.id, l.duree, l.realisateur, d.titre, d.image, l.synopsis, ";
        $req .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $req .= "from dvd l join document d on l.id=d.id ";
        $req .= "join genre g on g.id=d.idGenre ";
        $req .= "join public p on p.id=d.idPublic ";
        $req .= "join rayon r on r.id=d.idRayon ";
        $req .= "order by titre ";	
        return $this->conn->query($req);
    }

    /**
     * récupération de toutes les lignes de la table Revue et les tables associées
     * @return lignes de la requete
     */
    public function selectAllRevues(){
        $req = "Select l.id, l.periodicite, d.titre, d.image, l.delaiMiseADispo, ";
        $req .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $req .= "from revue l join document d on l.id=d.id ";
        $req .= "join genre g on g.id=d.idGenre ";
        $req .= "join public p on p.id=d.idPublic ";
        $req .= "join rayon r on r.id=d.idRayon ";
        $req .= "order by titre ";
        return $this->conn->query($req);
    }

    /**
     * récupération de tous les exemplaires d'une revue
     * @param string $id id de la revue
     * @return lignes de la requete
     */
    public function selectExemplairesRevue($id){
        $param = array(
                "id" => $id
        );
        $req = "Select e.id, e.numero, e.dateAchat, e.photo, e.idEtat ";
        $req .= "from exemplaire e join document d on e.id=d.id ";
        $req .= "where e.id = :id ";
        $req .= "order by e.dateAchat DESC";
        return $this->conn->query($req, $param);
    }

    /**
     * récupération de tous les abonnements d'une revue
     *
     * @param [type] $idRevue
     * @return lignes de la requete
     */
    public function selectAbonnementsRevue($idRevue){
        $param = array(
            "idRevue" => $idRevue
        );
        $req = "Select a.id, c.dateCommande, c.montant, a.dateFinAbonnement, a.idRevue ";
        $req .= "from abonnement a join commande c on a.id=c.id ";
        $req .= "where a.idRevue = :idRevue ";
        $req .= "order by c.dateCommande DESC";	
        return $this->conn->query($req, $param);
    }

    /**
     * récupération d'un utilisateur si les données correspondent
     *
     * @param [type] $champs
     * @return ligne de la requete
     */
    public function selectUtilisateur($champs)
    {
        $param = array(
            "mail" => $champs["mail"],
            "password" => $champs["password"]
        );
        $req = "Select u.id, u.nom, u.prenom, u.mail, u.idservice, s.libelle as service ";
        $req .= "from utilisateur u join service s on u.idservice=s.id ";
        $req .= "where u.mail = :mail ";
        $req .= "and u.password = :password ";
        $req .= "or u.nom = :mail ";
        $req .= "and u.password = :password";
        return $this->conn->query($req, $param);
    }

    /**
     * Retourne la plus grande id de la table commande
     *
     * @return lignes de la requete 
     */
    public function selectMaxCommande(){
        $req = "Select MAX(id) AS id FROM commande";
        return $this->conn->query($req);
    }

    /**
     * récupération de toutes les commandes d'une dvd_livre
     * @param string $idLivreDvd id du livre_dvd
     * @return lignes de la requete
     */
    public function selectCommandesDocument($idLivreDvd){
        $param = array(
                "idLivreDvd" => $idLivreDvd
        );
        $req = "Select cd.id, c.dateCommande, c.montant, cd.nbExemplaire, cd.idLivreDvd, ";
        $req .= "cd.idsuivi, s.etat ";
        $req .= "from commandedocument cd join commande c on cd.id=c.id ";
        $req .= "join suivi s on cd.idsuivi=s.id ";
        $req .= "where cd.idLivreDvd = :idLivreDvd ";
        $req .= "order by c.dateCommande DESC";	
        return $this->conn->query($req, $param);
    }

    /**
     * Suppresion de l'entitée composée commandeDocument dans la bdd
     *
     * @param [type] $champs nom et valeur de chaque champs de la ligne
     * @return true si l'ajout a fonctionné
     */
    public function deleteCommande($champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsCommandeDocument = [ "id" => $champs["Id"], "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"], "idsuivi" => $champs["IdSuivi"]];
        $result = $this->delete("commandedocument", $champsCommandeDocument);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->delete( "commande", $champsCommande);
    }

    /**
     * Suppresion de l'entitée composée abonnement dans la bdd
     *
     * @param [type] $champs nom et valeur de chaque champs de la ligne
     * @return true si l'ajout a fonctionné
     */
    public function deleteAbonnement($champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsAbonnement = [ "id" => $champs["Id"], "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]];
        $result = $this->delete("abonnement", $champsAbonnement);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->delete( "commande", $champsCommande);
    }

    /**
     * ajout d'une ligne dans une table
     * @param string $table nom de la table
     * @param array $champs nom et valeur de chaque champs de la ligne
     * @return true si l'ajout a fonctionné
     */	
    public function insertOne($table, $champs){
        if($this->conn != null && $champs != null){
            // construction de la requête
            $requete = "insert into $table (";
            foreach ($champs as $key => $value){
                $requete .= "$key,";
            }
            // (enlève la dernière virgule)
            $requete = substr($requete, 0, strlen($requete)-1);
            $requete .= ") values (";
            foreach ($champs as $key => $value){
                $requete .= ":$key,";
            }
            // (enlève la dernière virgule)
            $requete = substr($requete, 0, strlen($requete)-1);
            $requete .= ");";
            return $this->conn->execute($requete, $champs);
        }else{
            return null;
        }
    }

    /**
     * suppresion d'une ou plusieurs lignes dans une table
     * @param string $table nom de la table
     * @param array $champs nom et valeur de chaque champs
     * @return true si la suppression a fonctionné
     */	
    public function delete($table, $champs){
        if($this->conn != null){
            // construction de la requête
            $requete = "delete from $table where ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key and ";
            }
            // (enlève le dernier and)
            $requete = substr($requete, 0, strlen($requete)-5);
            return $this->conn->execute($requete, $champs);
        }else{
            return null;
        }
    }

    /**
     * Ajout de l'entitée composée commandeDocument dans la bdd
     *
     * @param [type] $champs nom et valeur de chaque champs de la ligne
     * @return true si l'ajout a fonctionné
     */
    public function insertCommande($champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsCommandeDocument = [ "id" => $champs["Id"], "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"], "idsuivi" => $champs["IdSuivi"]];
        $result = $this->insertOne("commande", $champsCommande);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->insertOne( "commandedocument", $champsCommandeDocument);
    }

    /**
     * Ajout de l'entitée composée abonnement dans la bdd
     *
     * @param [type] $champs
     * @return void
     */
    public function insertAbonnement($champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsAbonnement = [ "id" => $champs["Id"], "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]];
        $result = $this->insertOne("commande", $champsCommande);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->insertOne( "abonnement", $champsAbonnement);
    }

    /**
     * modification d'une ligne dans une table
     * @param string $table nom de la table
     * @param string $id id de la ligne à modifier
     * @param array $param nom et valeur de chaque champs de la ligne
     * @return true si la modification a fonctionné
     */
    public function updateOne($table, $id, $champs, $numero = null){
        if($this->conn != null && $champs != null){
            // construction de la requête
            $requete = "update $table set ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key,";
            }
            // (enlève la dernière virgule)
            $requete = substr($requete, 0, strlen($requete)-1);
            $champs["id"] = $id;
            $requete .= " where id=:id;";
            if($numero != null)
            {
                $requete = substr($requete, 0, strlen($requete)-1);
                $champs["numero"] = $numero;
                $requete .= " and numero=:numero;";
            }				
            return $this->conn->execute($requete, $champs);
        }else{
            return null;
        }
    }

    /**
     * Modification de l'entitée composée CommandeDocument dans la bdd
     *
     * @param [type] $champs nom et valeur de chaque champs de la ligne
     * @param [type] $id de l'element
     * @return true si l'ajout a fonctionné
     */
    public function updateCommande($id, $champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsCommandeDocument = [ "id" => $champs["Id"], "nbExemplaire" => $champs["NbExemplaire"],
                "idLivreDvd" => $champs["IdLivreDvd"], "idsuivi" => $champs["IdSuivi"]];
        $result = $this->updateOne("commande",$id, $champsCommande);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->updateOne( "commandedocument",$id, $champsCommandeDocument);
    }

    /**
     * Modification de l'entitée composée abonnement dans la bdd
     *
     * @param [type] $id
     * @param [type] $champs
     * @return void
     */
    public function updateAbonnement($id, $champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsAbonnement = [ "id" => $champs["Id"], "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]];
        $result = $this->updateOne("commande", $id, $champsCommande);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->updateOne( "abonnement",$id, $champsAbonnement); #updateExemplaire
    }

    /**
     * Modification de l'entitée composée abonnement dans la bdd
     *
     * @param [type] $id
     * @param [type] $champs
     * @return void
     */
    public function updateExemplaire($id, $champs)
    {
        $champsCommande = [ "id" => $champs["Id"], "dateCommande" => $champs["DateCommande"],
            "montant" => $champs["Montant"]];
        $champsAbonnement = [ "id" => $champs["Id"], "dateFinAbonnement" => $champs["DateFinAbonnement"],
                "idRevue" => $champs["IdRevue"]];
        $result = $this->updateOne("commande", $id, $champsCommande);
        if ($result == null || $result == false){
            return null;
        }
        return  $this->updateOne( "abonnement",$id, $champsAbonnement);
    }

}