const express = require('express');
const bodyParser = require('body-parser');

const app = express();
const port = 3000;

app.use(bodyParser.json());

let tasks = [
    {id:1, description: 'Je veux mange le paix'},
    {id:2, description: 'De preference chez moi !'},
    {id:3, description: 'Je vais manger seul'}
];

app.listen(port,()=>{
    console.log(`Serveur ecoutant sur le port ${port}`);
});

// liste de taches
app.get('/tasks',(req, res)=>{
    const taskReferences = tasks.map(task => `/task/${task.id}`);
    res.json(taskReferences);
});

// une tache specifique
app.get('/task/:id', (req, res)=>{
    const taskId = parseInt(req.params.id);
    const task = tasks.find(task => task.id === taskId);
    if(task){
        res.json(task);
    }else{
        res.status(404).json({error: 'Tache non trouvee'});
    }
});

// ajouter une tache
app.post('/tasks', (req, res)=>{
    const newTask = {
        id: tasks.length + 1,
        description: req.body.description
    };
    tasks.push(newTask);
    res.status(201).json({message: "Tache ajoutee avec succes", task: newTask});
});

// Modifier une tache
app.put('/task/:id', (req, res)=>{
    const taskId = parseInt(req.params.id);
    const task = tasks.find(task => task.id === taskId);
    if(task){
        task.description = req.body.description;
        res.json({message: "Tache modifiee avec succes", task: task});
    }else{
        res.status(404).json({error: 'Tache non trouvee'});
    }
});

// Supprimer une tache
app.delete('/task/:id', (req, res)=>{
    const taskId = parseInt(req.params.id);
    tasks = tasks.filter(task => task.id !== taskId);
    res.json({message: "Tache supprimee avec succes"});
});