import React from "react";
import {HashRouter, Route, Switch} from 'react-router-dom'
import ReactDOM from "react-dom";

import FetchWP from './utils/fetchWP';

import General from "./pages/General";

import Page2 from "./pages/Page2";

import Tabs from "./components/Tabs/Index";


class App extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            loader: false,
            saving: false,
            config: {
                general: {title: ''},
                page2: {title: ''}
            }, 
            csv_data: false
        }

        this.fetchWP = new FetchWP({
            restURL: window.masterstudy_object.root,
            restNonce: window.masterstudy_object.api_nonce,

        });

    }


    csvUploadHandler = (data, fileInfo) => {
        this.setState({
            csv_data: data
        })
    }


    componentDidMount() {
        this.fetchData();

    }

    componentWillUnmount() {

    }

    handleUpdate = () => {
        const {csv_data} = this.state;
        this.fetchWP.post('save', {'data': csv_data}).then(json => {
            console.log(json);
        }).catch(error => {
            alert("Some thing went wrong");
        })
    }

    SaveChanges = () => {

        const {config} = this.state;
        this.fetchWP.post('save', {'config': config}).then(json => {

        }).catch(error => {
            alert("Some thing went wrong");
        })
    }


    fetchData() {
        this.setState({
            loader: true,
        });

        this.fetchWP.get('config/')
            .then(
                (json) => {
                    this.setState({
                        loader: false,
                        config: json,
                    });
                });


    }

    render() {
        const {config} = this.state;
        return (
            <div>
                <HashRouter>
                    <Tabs/>
                    <Switch>
                        <Route
                            path="/"
                            exact
                            render={props =>
                                <General 
                                    config={config} 
                                    handleUpdate={this.handleUpdate}
                                    csvUploadHandler={this.csvUploadHandler}
                                />
                            }
                        />
                        
                    </Switch>
                </HashRouter>


            </div>
        )
    }

}


if (document.getElementById("masterstudy_ui_root")) {
    ReactDOM.render(<App/>, document.getElementById("masterstudy_ui_root"));
}

