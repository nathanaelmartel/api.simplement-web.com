# API

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg?style=flat-square)](http://www.gnu.org/licenses/gpl-3.0)

*Réalisé par [Nathanaël Martel](https://www.simplement-web.com/)*

## Périodes de soldes

Pour tous les départements : `https://api.simplement-web.com/soldes/2023`

Pour un département : `https://api.simplement-web.com/soldes/2023/07`

*Basé sur : [www.legifrance.gouv.fr](https://www.legifrance.gouv.fr/loda/id/LEGITEXT000038524717/)*

## Plan comptable général

Pour tous les codes : `https://api.simplement-web.com/plan-comptable-general`

Pour le code le plus proche : `https://api.simplement-web.com/12XXX`

Réponse : 
```
{
    "code":"12",
    "label":"Résultat de l'exercice"
}
```

S'il n'est pas trouvé, une erreur 404 est renvoyé, avec le code original et le label «&nbsp;indéterminée&nbsp;».

*Basé sur : [fr.wikipedia.org](https://fr.wikipedia.org/wiki/Plan_comptable_g%C3%A9n%C3%A9ral_(France))*