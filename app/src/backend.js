import React from "react";
import {HashRouter, Route, Switch} from 'react-router-dom'
import ReactDOM from "react-dom";

import FetchWP from './utils/fetchWP';

import General from "./pages/General";

import Tabs from "./components/Tabs/Index";

import Csvloader from './utils/csvloader';



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
            csv_data: false, 
            type: 'single_choice_question', 
            assets_url: window.masterstudy_object.assets_url, 
            upload_complete: false
        }

        this.fetchWP = new FetchWP({
            restURL: window.masterstudy_object.root,
            restNonce: window.masterstudy_object.api_nonce,

        });
        
        this.onChangeHandler = this.onChangeHandler.bind(this);
    }


    onChangeHandler = (e) => {
        this.setState({
            type: e.target.value
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
        this.setState({
            loader: true
        });
        const {csv_data, type} = this.state;
        this.fetchWP.post('save', {data: csv_data, type:type}).then(json => {
            console.log(json);
            this.setState({
                loader: false, 
                upload_complete: true
            });
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
                {this.state.loader ? <Csvloader /> : null}
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
                                    onChangeHandler={this.onChangeHandler}
                                    csvUploadHandler={this.csvUploadHandler}
                                    assets_url={this.state.assets_url}
                                    upload_complete={this.state.upload_complete}
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

