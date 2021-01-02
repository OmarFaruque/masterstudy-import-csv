import React, { Component } from 'react';
import style from '../backend.scss'
import Loader from 'react-loader-spinner'

export default class Csvloader extends Component {

    render(){
        return (
            <div className={style.loader}>
                <Loader
                        type="Grid"
                        color="#00BFFF"
                        height={100}
                        width={100}
                    />
            </div>
        )
    }
    
}